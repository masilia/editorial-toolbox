import React, { useState, useEffect, useCallback, useRef } from 'react';
import BlockPaletteItem from './BlockPaletteItem';
import DraggableBlockItem from './DraggableBlockItem';
import TrashZone from './TrashZone';
import EditModal from './EditModal';
import { apiUrl } from '../config';
import { apiFetch, isTrustedMessage } from '../api';

const notify = (type, message) => {
    const event = new CustomEvent('ibexa-notify', { detail: { message, label: type } });
    document.body.dispatchEvent(event);
};

const ToolboxSidebar = ({ locationId, visible, onClose, onBlocksChanged }) => {
    const [allowedBlockTypes, setAllowedBlockTypes] = useState([]);
    const [currentBlocks, setCurrentBlocks] = useState([]);
    const [draggedItem, setDraggedItem] = useState(null);
    const [dragOverIndex, setDragOverIndex] = useState(null);
    const [loading, setLoading] = useState(false);
    const [confirmDeleteId, setConfirmDeleteId] = useState(null);
    const [editModalOpen, setEditModalOpen] = useState(false);
    const [editModalUrl, setEditModalUrl] = useState(null);
    const [editModalLoading, setEditModalLoading] = useState(false);
    const [paletteExpanded, setPaletteExpanded] = useState(true);

    const pendingBlockRef = useRef(null);
    const dragFromIndexRef = useRef(null);
    const editModalOpenRef = useRef(false);
    const editModalUrlRef = useRef(null);
    const addBlockRelationByLocationRef = useRef(null);

    useEffect(() => {
        if (visible && locationId) {
            fetchBlocks();
        }
    }, [visible, locationId]);

    // Keep refs in sync so the once-registered message handler always sees latest values
    useEffect(() => { editModalOpenRef.current = editModalOpen; }, [editModalOpen]);
    useEffect(() => { editModalUrlRef.current = editModalUrl; }, [editModalUrl]);

    // Listen for postMessage from the COTF create/edit iframe
    useEffect(() => {
        const handleMessage = (event) => {
            if (!editModalOpenRef.current) return;
            if (!isTrustedMessage(event, editModalUrlRef.current)) return;
            console.log(event);
            const msg = event.data;

            // New block created or edited via ibexa.content.on_the_fly
            if (msg?.event === 'ibexa:content-create:success' || msg?.event === 'ibexa:content-edit:success') {
                editModalOpenRef.current = false;
                setEditModalOpen(false);
                setEditModalUrl(null);
                const position = pendingBlockRef.current;
                console.log(msg.contentId, position);
                pendingBlockRef.current = null;
                addBlockRelationByLocationRef.current?.(msg.contentId, position);
                // Cancel from create or edit
            } else if (msg === 'ibexa:content-edit:cancel' || msg?.event === 'ibexa:content-edit:cancel') {
                editModalOpenRef.current = false;
                setEditModalOpen(false);
                setEditModalUrl(null);
                pendingBlockRef.current = null;
            }
        };
        window.addEventListener('message', handleMessage);
        return () => window.removeEventListener('message', handleMessage);
    }, []); // register once — handler reads refs, not stale state

    const fetchBlocks = async () => {
        setLoading(true);
        try {
            const response = await apiFetch(apiUrl('blocks', { locationId }));
            if (response.status === 403) {
                notify('error', 'Access denied to page blocks.');
                return;
            }
            const data = await response.json();
            if (data.error) throw new Error(data.error);
            setAllowedBlockTypes(data.allowed_content_types || []);
            setCurrentBlocks(data.blocks || []);
        } catch (error) {
            notify('error', `Failed to load blocks: ${error.message}`);
        } finally {
            setLoading(false);
        }
    };

    const addBlockRelationByLocation = async (contentId, position) => { // eslint-disable-line
        try {
            const response = await apiFetch(apiUrl('blocksRelation', { locationId }), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ contentId, position }),
            });
            console.log(response);
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Failed to link block');
            notify('success', 'Block added successfully.');
            onBlocksChanged?.();
            window.location.reload();
        } catch (error) {
            notify('error', `Failed to link block: ${error.message}`);
        }
    };

    // Must be assigned AFTER addBlockRelationByLocation is defined (const arrow fns are not hoisted)
    addBlockRelationByLocationRef.current = addBlockRelationByLocation;

    const handleBlockAdd = useCallback(async (contentTypeIdentifier, position = null) => {
        setEditModalLoading(true);
        setEditModalOpen(true);
        try {
            const response = await apiFetch(apiUrl('blocks', { locationId }), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ contentTypeIdentifier, position }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Failed to prepare block');
            pendingBlockRef.current = position;  // store position; blockId resolved after publish
            setEditModalUrl(data.editUrl);
            setEditModalLoading(false);
        } catch (error) {
            notify('error', `Failed to create block: ${error.message}`);
            setEditModalOpen(false);
            setEditModalLoading(false);
        }
    }, [locationId]);

    const handleBlockRemove = useCallback(async (blockId) => {
        try {
            const response = await apiFetch(apiUrl('block', { locationId, blockContentId: blockId }), {
                method: 'DELETE',
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Failed to remove block');
            notify('success', 'Block removed.');
            setConfirmDeleteId(null);
            onBlocksChanged?.();
            window.location.reload();
        } catch (error) {
            notify('error', `Failed to remove block: ${error.message}`);
        }
    }, [locationId]);

    const handleBlockReorder = useCallback(async (newBlockIds) => {
        try {
            const response = await apiFetch(apiUrl('blocksOrder', { locationId }), {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ blockIds: newBlockIds }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Failed to reorder');
            notify('success', 'Block order saved.');
            onBlocksChanged?.();
            window.location.reload();
        } catch (error) {
            notify('error', `Failed to reorder blocks: ${error.message}`);
            fetchBlocks();
        }
    }, [locationId]);

    // Drag handlers — use refs to avoid state thrashing on dragover
    const handleDragStart = useCallback((type, data, index) => {
        setDraggedItem({ type, data });
        if (type === 'block') dragFromIndexRef.current = index;
    }, []);

    const handleDragEnd = useCallback(() => {
        setDraggedItem(null);
        setDragOverIndex(null);
        dragFromIndexRef.current = null;
    }, []);

    const handleBlockDragOver = useCallback((e, overIndex) => {
        if (!draggedItem) return;
        if (draggedItem.type !== 'block' && draggedItem.type !== 'palette') return;
        e.preventDefault();
        e.dataTransfer.dropEffect = draggedItem.type === 'palette' ? 'copy' : 'move';
        setDragOverIndex(overIndex);
    }, [draggedItem]);

    const handleBlockDrop = useCallback(async (e, overIndex) => {
        if (!draggedItem) return;
        e.preventDefault();
        setDragOverIndex(null);

        if (draggedItem.type === 'palette') {
            // Stop bubbling so the parent handleListEndDrop (position=null) doesn't also fire
            e.stopPropagation();
            dragFromIndexRef.current = null;
            setDraggedItem(null);
            await handleBlockAdd(draggedItem.data, overIndex);
            return;
        }

        if (draggedItem.type === 'block') {
            const fromIndex = dragFromIndexRef.current;
            if (fromIndex === null || fromIndex === overIndex) return;
            const reordered = [...currentBlocks];
            const [moved] = reordered.splice(fromIndex, 1);
            reordered.splice(overIndex, 0, moved);
            setCurrentBlocks(reordered);
            dragFromIndexRef.current = null;
            await handleBlockReorder(reordered.map(b => b.id));
        }
    }, [draggedItem, currentBlocks, handleBlockReorder, handleBlockAdd]);

    const handleListEndDragOver = useCallback((e) => {
        if (!draggedItem || draggedItem.type !== 'palette') return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
    }, [draggedItem]);

    const handleListEndDrop = useCallback(async (e) => {
        if (!draggedItem || draggedItem.type !== 'palette') return;
        e.preventDefault();
        setDraggedItem(null);
        await handleBlockAdd(draggedItem.data, null);
    }, [draggedItem, handleBlockAdd]);

    if (!visible) return null;

    return (
        <>
            <div className="atb-sidebar">
                <div className="atb-sidebar__header">
                    <h3>Page Blocks</h3>
                    <button className="atb-sidebar__close" onClick={onClose} aria-label="Close sidebar">×</button>
                </div>

                <div className="atb-sidebar__content">
                    <div className="atb-sidebar__section">
                        <button
                            className="atb-sidebar__section-toggle"
                            onClick={() => setPaletteExpanded(!paletteExpanded)}
                            aria-expanded={paletteExpanded}
                            type="button"
                        >
                            <h4>Add Block</h4>
                            <svg className="atb-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>
                        <div className={`atb-block-palette ${!paletteExpanded ? 'atb-block-palette--collapsed' : ''}`}>
                            {loading ? (
                                <div className="atb-loading">
                                    <div className="atb-skeleton" />
                                    <div className="atb-skeleton" />
                                    <div className="atb-skeleton" />
                                </div>
                            ) : allowedBlockTypes.length === 0 ? (
                                <div className="atb-empty">No block types available</div>
                            ) : (
                                allowedBlockTypes.map((type) => (
                                    <BlockPaletteItem
                                        key={type.identifier}
                                        typeIdentifier={type.identifier}
                                        typeName={type.name}
                                        onDragStart={() => handleDragStart('palette', type.identifier, null)}
                                        onDragEnd={handleDragEnd}
                                        onAdd={() => handleBlockAdd(type.identifier)}
                                    />
                                ))
                            )}
                        </div>
                    </div>

                    <div className="atb-sidebar__section">
                        <h4>Current Blocks ({currentBlocks.length})</h4>
                        <div
                            className={`atb-current-blocks${draggedItem?.type === 'palette' ? ' atb-current-blocks--palette-drag' : ''}`}
                            onDragOver={handleListEndDragOver}
                            onDrop={handleListEndDrop}
                        >
                            {currentBlocks.length === 0 && !loading && (
                                <div
                                    className={`atb-empty${draggedItem?.type === 'palette' ? ' atb-empty--drop-target' : ''}`}
                                    onDragOver={handleListEndDragOver}
                                    onDrop={handleListEndDrop}
                                >
                                    {draggedItem?.type === 'palette' ? 'Drop here to add' : 'No blocks on this page'}
                                </div>
                            )}
                            {currentBlocks.map((block, index) => (
                                <DraggableBlockItem
                                    key={block.id}
                                    block={block}
                                    index={index}
                                    isDragging={draggedItem?.type === 'block' && draggedItem.data.id === block.id}
                                    isDragOver={dragOverIndex === index}
                                    confirmDelete={confirmDeleteId === block.id}
                                    onDragStart={() => handleDragStart('block', block, index)}
                                    onDragEnd={handleDragEnd}
                                    onDragOver={(e) => handleBlockDragOver(e, index)}
                                    onDrop={(e) => handleBlockDrop(e, index)}
                                    onRemoveRequest={() => setConfirmDeleteId(block.id)}
                                    onRemoveConfirm={() => handleBlockRemove(block.id)}
                                    onRemoveCancel={() => setConfirmDeleteId(null)}
                                />
                            ))}
                            {currentBlocks.length > 0 && (
                                <div
                                    className={`atb-drop-end${draggedItem?.type === 'palette' ? ' atb-drop-end--active' : ''}`}
                                    onDragOver={handleListEndDragOver}
                                    onDrop={handleListEndDrop}
                                >
                                    {draggedItem?.type === 'palette' ? '↓ Drop to add at end' : ''}
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                <TrashZone
                    draggedItem={draggedItem}
                    onDrop={() => {
                        if (draggedItem?.type === 'block') {
                            setConfirmDeleteId(draggedItem.data.id);
                            setDraggedItem(null);
                        }
                    }}
                />
            </div>

            <EditModal
                open={editModalOpen}
                url={editModalUrl}
                loading={editModalLoading}
                onClose={() => {
                    setEditModalOpen(false);
                    setEditModalUrl(null);
                    pendingBlockRef.current = null;
                }}
            />
        </>
    );
};

export default ToolboxSidebar;