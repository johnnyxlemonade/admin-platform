/*!
 * Lemonade I18n
 *
 * Loads and applies localized admin dictionaries.
 *
 * @link      https://lemonadeframework.cz/
 * @author    Honza Mudrak <honzamudrak@gmail.com>
 * @copyright Copyright (c) 2026 Honza Mudrak. All rights reserved.
 * @license   Apache-2.0 - see LICENSE
 */
import { DomHelper } from "./lemonade-dom-helper.js";
import { EventHelper } from "./lemonade-event-helper.js";
import { StorageHelper } from "./lemonade-storage-helper.js";
    export class I18n {
        static async init(shell) {
            this.shell = shell || document.querySelector("[data-lemonade-admin-shell]") || document.querySelector("[data-lemonade-install-shell]");
            const explicitLocale = this.shell && this.shell.getAttribute("data-lemonade-locale");
            const storedLocale = StorageHelper.get("lemonade.locale");
            this.embeddedMessages = this.getEmbeddedMessages(document);

            if (this.embeddedMessages !== null) {
                const availableLocales = Object.keys(this.embeddedMessages);
                const requestedLocale = explicitLocale || storedLocale || document.documentElement.lang || "cs";
                const initialLocale = Object.prototype.hasOwnProperty.call(this.embeddedMessages, requestedLocale)
                    ? requestedLocale
                    : (availableLocales[0] || "cs");

                await this.render(initialLocale, this.embeddedMessages[initialLocale] || {}, document, null);
                this.persistLocale(initialLocale);
                this.bindLocaleSwitches();
                return;
            }

            const initialLocale = explicitLocale || storedLocale || document.documentElement.lang || "cs";
            const bundle = await this.loadBundle(initialLocale);

            await this.render(initialLocale, bundle.messages, document, bundle.navigationCatalog);
            this.persistLocale(initialLocale);
            this.bindLocaleSwitches();
        }

        static getEmbeddedMessages(context) {
            const node = (context || document).querySelector("[data-lemonade-i18n-embedded]");
            if (!node) {
                return null;
            }

            try {
                const messages = JSON.parse(node.textContent || "{}");

                return messages && typeof messages === "object" && !Array.isArray(messages) ? messages : {};
            } catch (error) {
                return {};
            }
        }

        static async loadMessages(locale, context) {
            return this.loadNamespaces(locale, context || document);
        }

        static async ensureNamespaces(resources, locale) {
            const requestedLocale = locale || this.locale || document.documentElement.lang || "cs";
            const definitions = {};
            (Array.isArray(resources) ? resources : []).forEach(function (resource) {
                if (!resource || typeof resource.namespace !== "string" || typeof resource.source !== "string" || !resource.namespace || !resource.source) {
                    return;
                }
                definitions[resource.namespace] = { source: resource.source, versions: resource.versions || {} };
            });

            this.registerNamespaceDefinitions(definitions);
            const settled = await Promise.allSettled(Object.keys(definitions).map(function (namespace) {
                return I18n.loadNamespace(namespace, definitions[namespace].source, requestedLocale, definitions[namespace].versions[requestedLocale])
                    .then(function (messages) { return { namespace: namespace, messages: messages }; });
            }));
            const messages = Object.assign({}, this.messages || {});
            settled.forEach(function (result) {
                if (result.status === "fulfilled") {
                    messages[result.value.namespace] = result.value.messages;
                    return;
                }
                I18n.reportLoadFailure(result.reason);
            });
            this.messages = messages;
        }

        static getNavigationSource(context) {
            const element = (context || document).querySelector("[data-lemonade-navigation-source]");
            return element ? element.getAttribute("data-lemonade-navigation-source") : "";
        }

        static async loadBundle(locale, context) {
            const source = this.getNavigationSource(context || document);
            const [namespaceResult, navigationResult] = await Promise.all([
                this.loadMessages(locale, context || document),
                source ? this.loadNavigationCatalog(source, locale) : Promise.resolve(null)
            ].map(function (promise) {
                return Promise.resolve(promise).then(function (value) {
                    return { ok: true, value: value };
                }, function (error) {
                    return { ok: false, error: error };
                });
            }));
            const namespaceLoad = namespaceResult.ok ? namespaceResult.value : { messages: {}, failures: [namespaceResult.error] };
            if (!namespaceResult.ok) {
                this.reportLoadFailure(namespaceResult.error);
            }
            if (!navigationResult.ok) {
                this.reportLoadFailure(navigationResult.error);
            }
            return {
                messages: namespaceLoad.messages,
                requiredFailures: namespaceLoad.failures,
                navigationCatalog: navigationResult.ok ? navigationResult.value : null,
            };
        }

        static getNamespaceDefinitions(context) {
            const definitions = {};
            const root = context || document;
            const registerDefinition = function (element) {
                const namespace = element.getAttribute("data-lemonade-i18n-namespace");
                const source = element.getAttribute("data-lemonade-i18n-namespace-source");
                let versions = {};
                try { versions = JSON.parse(element.getAttribute("data-lemonade-i18n-namespace-versions") || "{}"); } catch (error) {}

                if (namespace && source && !Object.prototype.hasOwnProperty.call(definitions, namespace)) {
                    definitions[namespace] = { source: source, versions: versions };
                }
            };

            if (root instanceof window.Element && root.matches("[data-lemonade-i18n-namespace][data-lemonade-i18n-namespace-source]")) {
                registerDefinition(root);
            }
            DomHelper.queryAll("[data-lemonade-i18n-namespace][data-lemonade-i18n-namespace-source]", root).forEach(registerDefinition);

            return definitions;
        }

        static registerNamespaceDefinitions(definitions) {
            Object.keys(definitions || {}).forEach(function (namespace) {
                I18n.activeNamespaceDefinitions[namespace] = definitions[namespace];
            });
        }

        static getTranslationKeyNamespaces(context) {
            const namespaces = {};
            const root = context || document;

            [["data-lemonade-i18n", "textContent"], ["data-lemonade-i18n-placeholder", "placeholder"], ["data-lemonade-i18n-title", "title"], ["data-lemonade-i18n-aria-label", "aria-label"]].forEach(function (definition) {
                DomHelper.queryAll(`[${definition[0]}]`, root).forEach(function (element) {
                    const key = element.getAttribute(definition[0]);
                    const namespace = key ? key.split(".")[0] : "";
                    if (!/^[a-z][a-z0-9_-]*$/.test(namespace)) {
                        return;
                    }

                    namespaces[namespace] = true;
                });
            });

            return Object.keys(namespaces);
        }

        static async loadClientResourceMap() {
            if (this.clientResourceMap) {
                return this.clientResourceMap;
            }

            if (!this.clientResourceMapLoading) {
                const source = document.querySelector("[data-lemonade-i18n-resource-map-source]")?.getAttribute("data-lemonade-i18n-resource-map-source");
                if (!source) {
                    throw new Error("Client translation resource map source is missing.");
                }
                this.clientResourceMapLoading = window.fetch(source, { headers: { Accept: "application/json" } })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error("Unable to load client translation resource map.");
                        }

                        return response.json();
                    })
                    .then(function (map) {
                        const resources = map && Array.isArray(map.resources) ? map.resources : [];
                        return resources.filter(function (resource) {
                            return resource
                                && typeof resource.namespace === "string"
                                && typeof resource.source === "string"
                                && /^[a-z][a-z0-9_-]*$/.test(resource.namespace)
                                && resource.source;
                        });
                    })
                    .then(function (resources) {
                        I18n.clientResourceMap = resources;
                        delete I18n.clientResourceMapLoading;
                        return resources;
                    })
                    .catch(function (error) {
                        delete I18n.clientResourceMapLoading;
                        throw error;
                    });
            }

            return this.clientResourceMapLoading;
        }

        static async loadNamespace(namespace, source, locale, version) {
            const encodedLocale = encodeURIComponent(locale);
            const url = source.replace("{locale}", encodedLocale).replace("{version}", encodeURIComponent(version || ""));
            const cacheKey = `${namespace}:${url}`;

            if (this.namespaceCache[cacheKey]) {
                return this.namespaceCache[cacheKey];
            }

            if (!this.namespaceLoading[cacheKey]) {
                this.namespaceLoading[cacheKey] = window.fetch(url, { headers: { Accept: "application/json" } })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error("Unable to load namespace translations.");
                        }

                        return response.json();
                    })
                    .then(function (messages) {
                        I18n.namespaceCache[cacheKey] = messages;
                        delete I18n.namespaceLoading[cacheKey];
                        return messages;
                    })
                    .catch(function (error) {
                        delete I18n.namespaceLoading[cacheKey];
                        throw error;
                    });
            }

            return this.namespaceLoading[cacheKey];
        }

        static async loadNavigationCatalog(source, locale) {
            const url = source.replace("{locale}", encodeURIComponent(locale));

            if (this.navigationCache[url]) {
                return this.navigationCache[url];
            }

            if (!this.navigationLoading[url]) {
                this.navigationLoading[url] = window.fetch(url, { headers: { Accept: "application/json" } })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error("Unable to load navigation catalog.");
                        }

                        return response.json();
                    })
                    .then(function (catalog) {
                        if (!catalog || !Array.isArray(catalog.groups) || !Array.isArray(catalog.items)) {
                            throw new Error("Invalid navigation catalog.");
                        }
                        I18n.navigationCache[url] = catalog;
                        delete I18n.navigationLoading[url];
                        return catalog;
                    })
                    .catch(function (error) {
                        delete I18n.navigationLoading[url];
                        throw error;
                    });
            }

            return this.navigationLoading[url];
        }

        static async loadNamespaces(locale, context) {
            const definitions = Object.assign({}, this.activeNamespaceDefinitions, this.getNamespaceDefinitions(context));
            this.registerNamespaceDefinitions(definitions);
            const requestedNamespaces = this.getTranslationKeyNamespaces(context).filter(function (namespace) {
                return !Object.prototype.hasOwnProperty.call(definitions, namespace);
            });
            const resources = requestedNamespaces.length === 0
                ? []
                : await this.loadClientResourceMap().catch(function () { return []; });

            resources.forEach(function (resource) {
                if (requestedNamespaces.includes(resource.namespace)) {
                    definitions[resource.namespace] = { source: resource.source, versions: resource.versions || {} };
                }
            });
            this.registerNamespaceDefinitions(definitions);
            const namespaces = {};
            const failures = [];
            const settled = await Promise.allSettled(Object.keys(definitions).map(function (namespace) {
                return I18n.loadNamespace(namespace, definitions[namespace].source, locale, definitions[namespace].versions[locale])
                    .then(function (messages) {
                        return { namespace: namespace, messages: messages };
                    });
            }));
            settled.forEach(function (result) {
                if (result.status === "fulfilled") {
                    namespaces[result.value.namespace] = result.value.messages;
                    return;
                }
                failures.push(result.reason);
                I18n.reportLoadFailure(result.reason);
            });

            return { messages: namespaces, failures: failures };
        }

        static t(key, params, messages) {
            const message = key.split(".").reduce(function (value, part) {
                return value && Object.prototype.hasOwnProperty.call(value, part) ? value[part] : null;
            }, messages || this.messages || {});
            if (typeof message !== "string") {
                return key;
            }
            return message.replace(/\{([^}]+)\}/g, function (match, name) {
                return params && params[name] !== undefined ? params[name] : match;
            });
        }

        static getTranslationParams(element) {
            const params = {};
            const prefix = "data-lemonade-i18n-param-";

            Array.prototype.forEach.call(element.attributes || [], function (attribute) {
                if (attribute.name.indexOf(prefix) !== 0) {
                    return;
                }

                params[attribute.name.slice(prefix.length)] = attribute.value;
            });

            return params;
        }

        static async setLocale(locale) {
            if (!locale || locale === this.locale) {
                return;
            }

            if (this.embeddedMessages !== null) {
                if (!Object.prototype.hasOwnProperty.call(this.embeddedMessages, locale)) {
                    return;
                }

                await this.render(locale, this.embeddedMessages[locale] || {}, document, null);
                this.persistLocale(locale);
                document.dispatchEvent(new CustomEvent("lemonade:locale:changed", { detail: { locale: locale } }));
                return;
            }

            const bundle = await this.loadBundle(locale);
            if (bundle.requiredFailures.length > 0) {
                return;
            }
            await this.render(locale, bundle.messages, document, bundle.navigationCatalog);
            this.persistLocale(locale);
            document.dispatchEvent(new CustomEvent("lemonade:locale:changed", { detail: { locale: locale } }));
        }

        static getLocale() {
            return this.locale;
        }

        static getIntlLocale(locale) {
            const selectedLocale = locale || this.locale;
            let intlLocale = selectedLocale;

            DomHelper.queryAll("[data-lemonade-locale-option]").some(function (option) {
                if (option.getAttribute("data-lemonade-locale-option") !== selectedLocale) {
                    return false;
                }

                intlLocale = option.getAttribute("data-lemonade-intl-locale") || selectedLocale;
                return true;
            });

            return intlLocale;
        }

        static formatDate(value, options, locale) {
            const fallback = typeof value === "string" ? value : "";

            if (!window.Intl || !window.Intl.DateTimeFormat || !value) {
                return fallback;
            }

            try {
                const date = value instanceof Date ? value : new Date(/^\d{4}-\d{2}-\d{2}$/.test(value) ? `${value}T00:00:00` : value);
                if (Number.isNaN(date.getTime())) {
                    return fallback;
                }

                const intlLocale = this.getIntlLocale(locale);
                const formatted = new window.Intl.DateTimeFormat(intlLocale, options || {
                    weekday: "long",
                    year: "numeric",
                    month: "long",
                    day: "numeric"
                }).format(date);

                return formatted.charAt(0).toLocaleUpperCase(intlLocale) + formatted.slice(1);
            } catch (error) {
                return fallback;
            }
        }

        static formatDateTime(value, locale) {
            return this.formatDate(value, {
                year: "numeric",
                month: "2-digit",
                day: "2-digit",
                hour: "2-digit",
                minute: "2-digit",
                second: "2-digit",
                hourCycle: "h23"
            }, locale);
        }

        static persistLocale(locale) {
            StorageHelper.set("lemonade.locale", locale);
            document.cookie = `lemonade_locale=${encodeURIComponent(locale)}; path=/; max-age=31536000; samesite=lax`;
        }

        static getNavigationLabel(catalog, collection, id) {
            const entry = catalog && Array.isArray(catalog[collection])
                ? catalog[collection].find(function (item) { return item.id === id; })
                : null;
            return entry && typeof entry.label === "string" ? entry.label : null;
        }

        static createRenderOperations(locale, messages, context, navigationCatalog) {
            const scope = context || document;
            const operations = [];

            DomHelper.queryAll("[data-lemonade-i18n]", scope).forEach(function (element) {
                const key = element.getAttribute("data-lemonade-i18n");
                const translation = this.t(key, this.getTranslationParams(element), messages);
                operations.push({ element: element, property: "textContent", value: translation === key ? element.textContent : translation });
            }, this);
            [["data-lemonade-i18n-placeholder", "placeholder"], ["data-lemonade-i18n-title", "title"], ["data-lemonade-i18n-aria-label", "aria-label"]].forEach(function (definition) {
                DomHelper.queryAll(`[${definition[0]}]`, scope).forEach(function (element) {
                    const key = element.getAttribute(definition[0]);
                    const translation = this.t(key, null, messages);
                    operations.push({ element: element, property: definition[1], value: translation === key ? element.getAttribute(definition[1]) : translation });
                }, this);
            }, this);
            DomHelper.queryAll("[data-lemonade-i18n-date]", scope).forEach(function (element) {
                const value = element.getAttribute("datetime") || element.getAttribute("data-lemonade-i18n-date");
                operations.push({ element: element, property: "textContent", value: this.formatDate(value, null, locale) });
            }, this);
            DomHelper.queryAll("[data-lemonade-i18n-datetime]", scope).forEach(function (element) {
                const value = element.getAttribute("datetime") || element.getAttribute("data-lemonade-i18n-datetime");
                operations.push({ element: element, property: "textContent", value: this.formatDateTime(value, locale) });
            }, this);
            [["data-lemonade-navigation-group-label", "groups"], ["data-lemonade-navigation-item-label", "items"]].forEach(function (definition) {
                DomHelper.queryAll(`[${definition[0]}]`, scope).forEach(function (element) {
                    const translation = this.getNavigationLabel(navigationCatalog, definition[1], element.getAttribute(definition[0]));
                    operations.push({ element: element, property: "textContent", value: translation || element.textContent });
                }, this);
            }, this);

            return operations;
        }

        static render(locale, messages, context, navigationCatalog) {
            const catalog = navigationCatalog === undefined ? this.navigationCatalog : navigationCatalog;
            const operations = this.createRenderOperations(locale, messages, context, catalog);
            const self = this;

            return new Promise(function (resolve) {
                window.requestAnimationFrame(function () {
                    operations.forEach(function (operation) {
                        operation.element[operation.property] = operation.value;
                    });

                    if (self.shell) {
                        self.shell.setAttribute("data-lemonade-locale", locale);
                    }
                    document.documentElement.lang = locale;
                    self.locale = locale;
                    self.messages = messages;
                    self.navigationCatalog = catalog || { groups: [], items: [] };
                    self.syncLocaleOptions(locale);
                    resolve();
                });
            });
        }

        static apply(context) {
            return this.render(this.locale, this.messages, context || document, this.navigationCatalog);
        }

        static reportLoadFailure(error) {
            if (window.console && typeof window.console.error === "function") {
                window.console.error("Unable to load an Admin translation resource.", error);
            }
        }

        static bindLocaleSwitches() {
            EventHelper.delegate(document, "click", "[data-lemonade-locale-option]", function (event, trigger) {
                event.preventDefault();
                void I18n.setLocale(trigger.getAttribute("data-lemonade-locale-option")).catch(function (error) {
                    I18n.reportLoadFailure(error);
                });
            });
        }

        static syncLocaleOptions(locale) {
            const activeLocale = locale || this.locale;
            DomHelper.queryAll("[data-lemonade-locale-option]").forEach(function (element) {
                const isActive = element.getAttribute("data-lemonade-locale-option") === activeLocale;
                element.classList.toggle("is-active", isActive);
                element.setAttribute("aria-pressed", String(isActive));
                DomHelper.queryAll("[data-lemonade-locale-active]", element).forEach(function (indicator) {
                    indicator.hidden = !isActive;
                });
            });
        }
    };

    I18n.namespaceCache = {};
    I18n.namespaceLoading = {};
    I18n.activeNamespaceDefinitions = {};
    I18n.embeddedMessages = null;
    I18n.clientResourceMap = null;
    I18n.clientResourceMapLoading = null;
    I18n.navigationCache = {};
    I18n.navigationLoading = {};
    I18n.navigationCatalog = { groups: [], items: [] };
