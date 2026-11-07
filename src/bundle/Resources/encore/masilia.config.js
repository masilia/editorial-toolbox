const path = require('path');

/**
 * Helpers to build the editorial toolbox with the host project's (front-end) Encore setup:
 *
 *   const masilia = require('./vendor/masilia/editorial-toolbox/src/bundle/Resources/encore/masilia.config.js');
 *   masilia.addEntry(Encore);                 // before Encore.getWebpackConfig()
 *   const config = Encore.getWebpackConfig();
 *   masilia.patchConfig(config);              // after it
 *
 * patchConfig() is needed because the package may be symlinked (Composer path repository): webpack then
 * resolves its imports (react, core-js polyfills injected by Babel...) from the real path, which is outside
 * the host project and cannot see the host's node_modules.
 */
function addEntry(Encore, entryName = 'admin-toolbar.js') {
    Encore.addEntry(entryName, [path.resolve(__dirname, '../public/js/index.js')]);

    return Encore;
}

function patchConfig(webpackConfig, hostNodeModules = path.resolve(process.cwd(), 'node_modules')) {
    webpackConfig.resolve = webpackConfig.resolve || {};
    const modules = webpackConfig.resolve.modules || ['node_modules'];

    if (!modules.includes(hostNodeModules)) {
        webpackConfig.resolve.modules = [...modules, hostNodeModules];
    }

    return webpackConfig;
}

module.exports = { addEntry, patchConfig };
