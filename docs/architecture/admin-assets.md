# Admin assety

## Ownership a distribuce

Admin package vlastni `source`, Vite vstupy, SCSS, fonty, ikony, obrazky a hotovou distribuci v `src/Resources/public`. Tento adresar obsahuje hashovane JS a CSS, staticke soubory i `.vite/manifest.json` a je soucasti Composer distribuce.

Host nevlastni Admin zdroje ani jejich build. Jeho public root je pouze deploy destination. Prikaz `admin:assets:publish` validuje package manifest, zkopiruje celou distribuci do hostem nastaveneho `public/assets/admin` a pred kopii odstrani pouze zastarale soubory teto destination. Prikaz nespousti Node ani Vite, takze produkcni deploy muze po `composer install` publikovat predpripravenou distribuci bez Node toolchainu.

`AdminAssetConfiguration` je hostem vlastneny kontrakt pro verejnou URL a relativni destination v public rootu. Vychodzi host muze poskytnout `/assets/admin/` a `assets/admin`; jiny host muze pouzit jinou verejnou cestu. `AdminAssetManifest` cte pouze hostem publikovany manifest. Admin, Auth i Installer z nej pouzivaji stejne stabilni entry a staticke pre-paint assety.

## Vyvoj

Z package rootu `npm run build` sestavi `source` do package distribuce. `npm run watch` sleduje Admin zdroje a drzi package dist aktualni. Host si pro lokalni UX publikuje aktualni obsah explicitne prikazem `admin:assets:publish`.

Frontend build a watch zustavaji oddelene host workflow pro hostem vlastnene frontend zdroje a public assety. Nemeni Admin dist ani jeho destination. Host branding zustava frontend host assetem.

## Klientsky runtime

Bootstrap zustava package development zavislosti pro SCSS/CSS a Bootstrap Icons, ale Admin runtime neimportuje Bootstrap JavaScript ani Popper. Native `<dialog>` Modal, Dropdown, Tabs a Collapse jsou Admin-owned komponenty; Admin/Auth Theme dropdown sdili stejny Dropdown contract.
