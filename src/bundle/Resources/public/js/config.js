/**
 * Runtime configuration injected by the server (see `masilia_editorial_toolbox()` Twig function):
 * API URL templates such as `/page/{locationId}/blocks`. Defaults keep the historical `/admin/api` paths.
 */
const DEFAULT_ENDPOINTS = {
    csrf: '/admin/api/csrf-token',
    draftEdit: '/admin/api/in-context/edit/{contentId}/{languageCode}/{locationId}',
    draftDiscard: '/admin/api/in-context/discard/{contentId}/{languageCode}',
    translationOptions: '/admin/api/translation/options/{contentId}/{languageCode}',
    blocks: '/admin/api/page/{locationId}/blocks',
    blocksRelation: '/admin/api/page/{locationId}/blocks/relation',
    blocksOrder: '/admin/api/page/{locationId}/blocks/order',
    block: '/admin/api/page/{locationId}/blocks/{blockContentId}',
};

let endpoints = { ...DEFAULT_ENDPOINTS };

export function setConfig(config = {}) {
    endpoints = { ...DEFAULT_ENDPOINTS, ...(config.endpoints || {}) };
}

export function apiUrl(name, params = {}) {
    return endpoints[name].replace(/\{(\w+)\}/g, (_, key) => encodeURIComponent(params[key]));
}
