# Admin request flow

`AdminRoutingConfiguration` defines the Admin base path and defaults to `/admin`. `AdminRoutePrefixRegistrar` applies it to all registered `AdminRouteRegistrarInterface` implementations, so modules declare relative paths and named routes.

## HTML pages

`ModuleController` resolves a module URL segment, checks module access and the required page capability, and calls the registered page provider. Full-page create and edit requests use `EditorDispatcher`; validation failure renders status 422, while a successful mutation redirects.

## JSON endpoints

`DataGridController` validates a query against the registered `DataGridDefinition` and returns columns, filters, rows, sorting, and pagination. `ModuleActionController` passes mutations to `ModuleActionDispatcher`, which checks the registered action permission before invoking its handler.

Admin JSON errors use `HttpStatusCode` and `AdminErrorCode`: invalid input returns 422, forbidden access returns 403, missing capabilities return 404, and lock or state conflicts return 409.

## Modal editors and locks

Shared modal editor routes are `GET /api/modal/{module}/create` and `GET /api/modal/{module}/{id}/edit`. `EditorModalController` resolves the module, checks the registered capability, loads the editor, and returns `modalHtml`. Saving uses the standard module action transport.

Editor lock release and cleanup use the internal `/api/editor/...` endpoints. Their client derives the configured Admin prefix from the current Admin URL.
