# UNL Site Validator

## A Note on LESS/CSS
All CSS is created with the LESS pre-processor. Do not modify the CSS files, as they will be overwritten by the LESS builds.

WDN Template _mixins are required:
`ln -s /path/to/UNL_WDNTemplates/wdn/templates_3.1/less/_mixins wdn_mixins`

### CSS Building
To build CSS files, you must have LESS installed:
`npm install -g less`

Then, to build the `main.css` file:
`lessc www/less/main.less www/css/main.css --compress`

## Javascript
Compression and mangling handled with uglify:
`npm install uglify-js -g`

Combine, compress and mangle:
`uglifyjs www/js/lib/handlebars-1.0.0-rc3.js www/js/main.js -o www/js/main.min.js -c -m`

#### Sublime Text 2 Build
1. Use the `(less2css)[https://github.com/timdouglas/sublime-less2css]` Build Process
2. Local Sublime Text 2 Settings (via "Preferences" -> "Package Settings" -> "Less2Css" -> "Settings - User")

```
    {
      "lessBaseDir": "./www/less",
      "outputDir": "./www/css",
      "minify": true,
      "autoCompile": true,
      "showErrorWithWindow": true,
      "main_file": "main.less"
    }
```