import React from 'react';

/**
 * The toolbox draws its own icons (inline SVG, 24x24, stroked with currentColor) so that it does not depend on
 * an icon font of the host site: a theme without Font Awesome would otherwise show empty buttons.
 */
const GLYPHS = {
    edit: (
        <>
            <path d="M12 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-6" />
            <path d="M18.5 2.5l3 3L12 15l-4 1 1-4z" />
        </>
    ),
    outline: <rect x="3" y="3" width="18" height="18" rx="2" strokeDasharray="3 3" />,
    grid: (
        <>
            <rect x="3" y="3" width="18" height="18" rx="2" />
            <path d="M3 12h18M12 3v18" />
        </>
    ),
    blocks: (
        <>
            <rect x="3" y="4" width="18" height="16" rx="2" />
            <path d="M15 4v16" />
        </>
    ),
    tools: (
        <>
            <path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0" />
            <circle cx="16" cy="6" r="2" />
            <circle cx="10" cy="12" r="2" />
            <circle cx="18" cy="18" r="2" />
        </>
    ),
    dashboard: (
        <>
            <rect x="3" y="3" width="7" height="9" rx="1" />
            <rect x="14" y="3" width="7" height="5" rx="1" />
            <rect x="14" y="12" width="7" height="9" rx="1" />
            <rect x="3" y="16" width="7" height="5" rx="1" />
        </>
    ),
    reload: (
        <>
            <path d="M20 11a8 8 0 1 0-2.3 5.7" />
            <path d="M20 4v7h-7" />
        </>
    ),
    logout: (
        <>
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
            <path d="M16 17l5-5-5-5M21 12H9" />
        </>
    ),
    user: (
        <>
            <circle cx="12" cy="8" r="4" />
            <path d="M4 21a8 8 0 0 1 16 0" />
        </>
    ),
    'chevron-left': <path d="M15 6l-6 6 6 6" />,
    'chevron-right': <path d="M9 6l6 6-6 6" />,
    expand: <path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7" />,
    compress: <path d="M4 14h6v6M20 10h-6V4M14 10l7-7M3 21l7-7" />,
    spinner: <path d="M12 3a9 9 0 1 0 9 9" />,
};

export default function Icon({ name, className = '', spin = false, style }) {
    const glyph = GLYPHS[name];
    if (!glyph) return null;

    const classes = ['atb-icon', spin ? 'atb-icon--spin' : '', className].filter(Boolean).join(' ');

    return (
        <svg className={classes} viewBox="0 0 24 24" aria-hidden="true" focusable="false" style={style}>
            {glyph}
        </svg>
    );
}
