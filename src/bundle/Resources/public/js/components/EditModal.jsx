import React, { useState, useEffect, useRef } from 'react';
import Icon from './Icon';

export default function EditModal({ open, url, loading, onClose }) {
    const [fs, setFs] = useState(false);
    const iframeRef = useRef(null);

    // Cross-origin iframe: signal fullscreen to the iframe via postMessage.
    // The iframe's COTF template listens for `atb:fs` and toggles body class.
    useEffect(() => {
        const iframe = iframeRef.current;
        if (!iframe || !url) return;

        let targetOrigin;
        try { targetOrigin = new URL(iframe.src).origin; } catch { return; }

        // Post only to the page we opened. Until it has loaded the iframe's window is the initial blank one, which belongs to
        // OUR origin, and the browser refuses ("The target origin provided ... does not match the recipient window's origin");
        // so does a frame that ended up on another page. A window of another origin than ours cannot be read: that is the
        // admin page (on its own host), so post to it. A readable one is ours or blank: wait for the load event.
        const send = () => {
            const win = iframe.contentWindow;
            if (!win) return;

            let frameOrigin = null;
            try { frameOrigin = win.location.origin; } catch { /* cross-origin: the admin page */ }
            if (frameOrigin !== null && frameOrigin !== targetOrigin) return;

            win.postMessage({ event: 'atb:fs', fullscreen: fs }, targetOrigin);
        };

        send();
        iframe.addEventListener('load', send);
        return () => iframe.removeEventListener('load', send);
    }, [fs, url, loading]);

    if (!open) return null;

    return (
        <div className="atb-modal-overlay">
            <div className={`atb-modal-container${fs ? ' atb-modal-container--fullscreen' : ''}`}>
                <div className="atb-modal-header">
                    <span className="atb-modal-title">In-Context Content Editor</span>
                    <div className="atb-modal-actions">
                        <button
                            className="atb-modal-fs"
                            onClick={() => setFs((v) => !v)}
                            aria-label={fs ? 'Exit fullscreen' : 'Enter fullscreen'}
                            title={fs ? 'Exit fullscreen' : 'Fullscreen'}
                        >
                            <Icon name={fs ? 'compress' : 'expand'} />
                        </button>
                        <button
                            className="atb-modal-close"
                            onClick={() => { onClose(); }}
                            aria-label="Close editor"
                        >
                            &times;
                        </button>
                    </div>
                </div>
                <div className="atb-modal-body">
                    {loading ? (
                        <div className="atb-modal-loader">
                            <Icon name="spinner" spin />
                            <span>Preparing edit draft...</span>
                        </div>
                    ) : (
                        <iframe
                            ref={iframeRef}
                            src={url}
                            className="atb-modal-iframe"
                            title="Ibexa Content Editor"
                        />
                    )}
                </div>
            </div>
        </div>
    );
}
