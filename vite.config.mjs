import { defineConfig } from "vite";
import { cpSync, copyFileSync, mkdirSync, rmSync } from "node:fs";
import { resolve } from "node:path";

const packagePath = (path) => resolve(import.meta.dirname, path);
const distributionPath = packagePath("src/Resources/public");

const copyStaticAssets = () => {
    rmSync(packagePath("src/Resources/public/images"), { recursive: true, force: true });
    rmSync(packagePath("src/Resources/public/js/core"), { recursive: true, force: true });
    cpSync(packagePath("source/images"), packagePath("src/Resources/public/images"), { recursive: true });
    mkdirSync(packagePath("src/Resources/public/js/core"), { recursive: true });
    copyFileSync(packagePath("source/js/core/lemonade-theme-init.js"), packagePath("src/Resources/public/js/core/lemonade-theme-init.js"));
    copyFileSync(packagePath("source/js/core/lemonade-sidebar-init.js"), packagePath("src/Resources/public/js/core/lemonade-sidebar-init.js"));
};

export default defineConfig(({ mode }) => ({
    base: "./",
    css: {
        preprocessorOptions: {
            scss: {
                quietDeps: true
            }
        }
    },
    plugins: [{
        name: "lemonade-admin-static-assets",
        writeBundle() {
            copyStaticAssets();
        }
    }],
    build: {
        cssCodeSplit: true,
        emptyOutDir: mode === "production",
        manifest: true,
        minify: mode === "production",
        outDir: distributionPath,
        rolldownOptions: {
            input: {
                admin: packagePath("source/js/entries/admin.js"),
                auth: packagePath("source/js/entries/auth.js"),
                installer: packagePath("source/js/entries/installer.js"),
                vendor: packagePath("source/scss/vendor.scss")
            },
            output: {
                hashCharacters: "hex",
                assetFileNames: "assets/[name]-[hash:20][extname]",
                chunkFileNames: "assets/[name]-[hash:20].js",
                entryFileNames: "assets/[name]-[hash:20].js"
            }
        },
        sourcemap: false
    },
    publicDir: false
}));
