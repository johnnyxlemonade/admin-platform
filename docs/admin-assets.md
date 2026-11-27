# Admin assets

The package owns its Vite entry points, JavaScript, SCSS, fonts, icons, images, and build tooling. Run the following command in the package root:

```sh
npm run build
```

The build creates the ignored distribution in `src/Resources/public`, including hashed assets and `.vite/manifest.json`. Release packaging or CI must build this distribution before a host publishes it.

The host publishes the prebuilt distribution into its public root with:

```sh
php vendor/bin/lemonade admin:assets:publish
```

The command validates and copies the existing distribution; it does not run Node or Vite. `AdminAssetConfiguration` defaults to `/assets/admin/` and the `assets/admin` directory under the host public root. A host may override both values.

Host frontend assets use a separate build. They do not replace or modify the Admin distribution.
