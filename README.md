# Lemonade Admin Platform

\`johnnyxlemonade/admin-platform\` je znovupoužitelná server-rendered administrační
platforma pro aplikace nad Lemonade Framework. Poskytuje AdminEditor, DataGrid,
integraci oprávnění a autorizace, discovery modulů, navigaci, dashboard widgety
a notifikace.

Platforma také vlastní sdílenou upload a file-management capability nad
\`system_file\`. URL obrázků a jejich varianty dodává \`johnnyxlemonade/admin-image\`.

## Instalace

Balíček vyžaduje PHP \`>=8.3 <8.6\`, \`johnnyxlemonade/framework\` a
\`johnnyxlemonade/admin-image\`.

    composer require johnnyxlemonade/admin-platform:dev-main

V host aplikaci zaregistrujte \`Lemonade\Admin\AdminPackageServiceProvider\` a
poskytněte konfiguraci routingu, assetů a brandingu vlastněnou hostem.

## Vývoj a QA

Při změně zdrojových assetů:

    npm install
    npm run build

Kontroly z rootu balíčku:

    composer cs:check
    composer stan
    composer test
    composer qa
