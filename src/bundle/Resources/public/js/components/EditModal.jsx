import React, { useState, useEffect, useRef } from 'react';

export default function EditModal({ open, url, loading, onClose }) {
    const [fs, setFs] = useState(false);
    const iframeRef = useRef(null);

    // Cross-origin iframe: signal fullscreen to the iframe via postMessage.
    // The iframe's COTF template listens for `atb:fs` and toggles body class.
    useEffect(() => {
        const iframe = iframeRef.current;
        if (!iframe || !iframe.contentWindow) return;

        let targetOrigin = '*';
        try { targetOrigin = new URL(iframe.src).origin; } catch {}

        const send = () => {
            try {
                iframe.contentWindow.postMessage({ event: 'atb:fs', fullscreen: fs }, targetOrigin);
            } catch {}
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
                            <i
                                className={`fa-solid ${fs ? 'fa-down-left-and-up-right-to-center' : 'fa-up-right-and-down-left-from-center'}`}
                                aria-hidden="true"
                            ></i>
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
                            <i className="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
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
