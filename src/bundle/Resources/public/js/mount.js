/**
 * Minimal React "island" mounting: finds mount points and lazily loads + renders a component into each,
 * passing the element's data-* attributes (JSON-parsed when possible) as props.
 */
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';

export function mountIsland(selector, componentLoader) {
    document.querySelectorAll(selector).forEach(async (el) => {
        try {
            const module = await componentLoader();
            const Component = module.default || module;

            const props = {};
            for (const [key, value] of Object.entries(el.dataset)) {
                try {
                    props[key] = JSON.parse(value);
                } catch {
                    props[key] = value;
                }
            }

            createRoot(el).render(createElement(Component, props));
        } catch (error) {
            console.error(`[editorial-toolbox] failed to mount "${selector}"`, error);
        }
    });
}
