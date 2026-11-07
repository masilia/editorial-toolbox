import React, { useState, useEffect, useCallback } from 'react';
import BlockBadge from './BlockBadge';
import EditModal from './EditModal';
import ToolboxSidebar from './ToolboxSidebar';
import { apiUrl } from '../config';
import { apiFetch, isTrustedMessage } from '../api';
import Icon from './Icon';

export default function AdminToolbar({ adminBase }) {
    const [expanded, setExpanded] = useState(false);
    const [outline, setOutline] = useState(false);
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [activeBadge, setActiveBadge] = useState(null);
    const [dashboardUrl, setDashboardUrl] = useState('#');
    const [pageMeta, setPageMeta] = useState(null);
    const [locationId, setLocationId] = useState(null);

    // Modal and iframe states
    const [modalOpen, setModalOpen] = useState(false);
    const [iframeUrl, setIframeUrl] = useState(null);
    const [loading, setLoading] = useState(false);
    const [draftInfo, setDraftInfo] = useState(null);

    useEffect(() => {
        const pageEl = document.querySelector('main#main-content[data-admin]');
        if (pageEl) {
            try {
                const meta = JSON.parse(pageEl.dataset.admin);
                setPageMeta(meta);
                if (meta.dashboard_url) setDashboardUrl(meta.dashboard_url);
                if (meta.location_id) setLocationId(meta.location_id);
            } catch {
                // ignore
            }
        }
    }, []);

    useEffect(() => {
        const handleMessage = (event) => {
            if (!isTrustedMessage(event, iframeUrl)) return;
            const msg = event.data;
            if (msg === 'ibexa:content-edit:success' || msg?.event === 'ibexa:content-edit:success') {
                setDraftInfo(null);
                setModalOpen(false);
                setIframeUrl(null);
                window.location.reload();
            } else if (msg === 'ibexa:content-edit:cancel' || msg?.event === 'ibexa:content-edit:cancel') {
                discardDraft();
                setModalOpen(false);
                setIframeUrl(null);
            }
        };
        window.addEventListener('message', handleMessage);
        return () => window.removeEventListener('message', handleMessage);
    }, [draftInfo, iframeUrl]);

    const discardDraft = useCallback(() => {
        if (draftInfo) {
            apiFetch(apiUrl('draftDiscard', { contentId: draftInfo.contentId, languageCode: draftInfo.language }), { method: 'DELETE' })
                .catch(() => {});
            setDraftInfo(null);
        }
    }, [draftInfo]);

    const handleEditClick = useCallback((meta) => {
        const { content_id, location_id , current_language} = meta;
        const htmlLang = current_language || document.documentElement.lang || 'eng-GB';

        setLoading(true);
        setModalOpen(true);
        setActiveBadge(null); // Hide badge when editor opens

        apiFetch(apiUrl('draftEdit', { contentId: content_id, languageCode: htmlLang, locationId: location_id }), { method: 'POST' })
            .then((res) => {
                if (!res.ok) throw new Error('Error creating draft');
                return res.json();
            })
            .then((data) => {
                setIframeUrl(data.editUrl);
                setDraftInfo({ contentId: content_id, language: htmlLang });
                setLoading(false);
            })
            .catch((err) => {
                console.error(err);
                alert('Could not initialize edit draft. Please try again or use the Back-Office.');
                setLoading(false);
                setModalOpen(false);
            });
    }, []);

    const getBlocks = useCallback(() => {
        return Array.from(document.querySelectorAll('[data-admin]'));
    }, []);

    // Re-scan [data-admin] elements when React re-renders the observatory
    const [domRevision, setDomRevision] = useState(0);
    useEffect(() => {
        if (!outline) return;
        const observer = new MutationObserver(() => setDomRevision((r) => r + 1));
        observer.observe(document.body, { childList: true, subtree: true });
        return () => observer.disconnect();
    }, [outline]);

    useEffect(() => {
        const blocks = getBlocks();
        blocks.forEach((el) => {
            el.classList.toggle('atb-block--outlined', outline);
        });

        if (!outline) {
            setActiveBadge(null);
        }
    }, [outline, getBlocks, domRevision]);

    useEffect(() => {
        if (!outline) return;

        const handlers = [];

        getBlocks().forEach((el) => {
            const handleClick = (e) => {
                e.stopPropagation();
                try {
                    const meta = JSON.parse(el.dataset.admin);
                    const rect = el.getBoundingClientRect();
                    // A full-width block starts at x = 0, i.e. under the toolbar: keep the badge to its right, and
                    // inside the visible part of a block that starts above the viewport.
                    const toolbar = document.querySelector('.admin-toolbar');
                    const minLeft = toolbar ? toolbar.getBoundingClientRect().right + 12 : 0;
                    setActiveBadge({
                        meta,
                        top: Math.max(rect.top, 0) + window.scrollY + 8,
                        left: Math.max(minLeft, Math.min(rect.left + 70, window.innerWidth - 320)),
                    });
                } catch {
                    // ignore parse errors
                }
            };
            el.addEventListener('click', handleClick);
            handlers.push({ el, handleClick });
        });

        const handleDocClick = () => setActiveBadge(null);
        document.addEventListener('click', handleDocClick);

        return () => {
            handlers.forEach(({ el, handleClick }) => el.removeEventListener('click', handleClick));
            document.removeEventListener('click', handleDocClick);
        };
    }, [outline, getBlocks, domRevision]);

    const blockCount = getBlocks().length;

    const cls = [
        'admin-toolbar',
        expanded ? 'admin-toolbar--expanded' : '',
        outline ? 'admin-toolbar--active' : '',
    ].filter(Boolean).join(' ');

    return (
        <>
            <div className={cls}>
                {/* Brand */}
                <div className="admin-toolbar__brand">
                    <Icon name="tools" className="admin-toolbar__icon" />
                    <span className="admin-toolbar__label">Admin</span>
                </div>

                {/* Actions */}
                <div className="admin-toolbar__actions">
                    <button
                        className={`admin-toolbar__btn ${outline ? 'admin-toolbar__btn--on' : ''}`}
                        onClick={() => setOutline((v) => !v)}
                        aria-label={outline ? 'Disable block outlines' : 'Enable block outlines'}
                    >
                        <Icon name={outline ? 'grid' : 'outline'} />
                        <span className="admin-toolbar__btn-text">
                            {outline ? 'Outlines ON' : 'Outlines'}
                        </span>
                        {outline && <span className="admin-toolbar__count">{blockCount}</span>}
                        <span className="atb-tip">
                            {outline ? `Outlines ON — ${blockCount} block${blockCount !== 1 ? 's' : ''}` : 'Toggle block outlines'}
                        </span>
                    </button>

                    <button
                        className={`admin-toolbar__btn ${sidebarOpen ? 'admin-toolbar__btn--on' : ''}`}
                        onClick={() => setSidebarOpen((v) => !v)}
                        aria-label={sidebarOpen ? 'Hide block palette' : 'Show block palette'}
                    >
                        <Icon name="blocks" />
                        <span className="admin-toolbar__btn-text">Blocks</span>
                        <span className="atb-tip">{sidebarOpen ? 'Hide blocks' : 'Show blocks'}</span>
                    </button>

                    {pageMeta && (
                        <button
                            className="admin-toolbar__btn"
                            onClick={() => handleEditClick(pageMeta)}
                            aria-label="Edit this page on-the-fly"
                            type="button"
                        >
                            <Icon name="edit" />
                            <span className="admin-toolbar__btn-text">Edit page</span>
                            <span className="atb-tip">Edit this page on-the-fly</span>
                        </button>
                    )}

                    <div className="admin-toolbar__separator" />

                    <a
                        className="admin-toolbar__btn"
                        href={dashboardUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="Open Back-Office dashboard"
                    >
                        <Icon name="dashboard" />
                        <span className="admin-toolbar__btn-text">Back-Office</span>
                        <span className="atb-tip">Open Back-Office</span>
                    </a>

                    <button
                        className="admin-toolbar__btn"
                        onClick={() => window.location.reload()}
                        aria-label="Reload the current page"
                    >
                        <Icon name="reload" />
                        <span className="admin-toolbar__btn-text">Reload</span>
                        <span className="atb-tip">Reload page</span>
                    </button>

                    <div className="admin-toolbar__separator" />

                    <a
                        className="admin-toolbar__btn admin-toolbar__btn--danger"
                        href="/logout"
                        aria-label="Log out"
                    >
                        <Icon name="logout" />
                        <span className="admin-toolbar__btn-text">Log out</span>
                        <span className="atb-tip">Log out</span>
                    </a>
                </div>

                {/* Environment */}
                <div className="admin-toolbar__env">
                    <span className="admin-toolbar__env-badge">
                        <Icon name="user" style={{ fontSize: 11 }} />
                    </span>
                    <span className="admin-toolbar__env-label">Editor mode</span>
                </div>

                {/* Expand / collapse toggle */}
                <button
                    className="admin-toolbar__toggle"
                    onClick={() => setExpanded(v => !v)}
                    aria-label={expanded ? 'Collapse sidebar' : 'Expand sidebar'}
                >
                    <Icon name={expanded ? 'chevron-left' : 'chevron-right'} />
                    <span className="atb-tip">{expanded ? 'Collapse' : 'Expand sidebar'}</span>
                </button>
            </div>

            {activeBadge && (
                <BlockBadge
                    block={activeBadge}
                    onClose={() => setActiveBadge(null)}
                    onEdit={handleEditClick}
                />
            )}

            <EditModal
                open={modalOpen}
                url={iframeUrl}
                loading={loading}
                onClose={() => { discardDraft(); setModalOpen(false); setIframeUrl(null); }}
            />

            <ToolboxSidebar
                locationId={locationId}
                visible={sidebarOpen}
                onClose={() => setSidebarOpen(false)}
                onBlocksChanged={() => {}}
            />
        </>
    );
}
