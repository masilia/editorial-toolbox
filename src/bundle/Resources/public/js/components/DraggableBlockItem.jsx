import React from 'react';
import BlockIcon from './BlockIcon';

const DraggableBlockItem = ({
    block, index,
    isDragging, isDragOver, confirmDelete,
    onDragStart, onDragEnd, onDragOver, onDrop,
    onRemoveRequest, onRemoveConfirm, onRemoveCancel,
}) => {
    const handleDragStart = (e) => {
        e.dataTransfer.setData('application/json', JSON.stringify(block));
        e.dataTransfer.effectAllowed = 'move';
        onDragStart();
    };

    const cls = [
        'atb-block-item',
        isDragging ? 'atb-block-item--dragging' : '',
        isDragOver ? 'atb-block-item--drag-over' : '',
    ].filter(Boolean).join(' ');

    return (
        <div
            className={cls}
            draggable
            onDragStart={handleDragStart}
            onDragEnd={onDragEnd}
            onDragOver={onDragOver}
            onDrop={onDrop}
            data-block-id={block.id}
        >
            <span className="atb-block-item__handle" title="Drag to reorder" />
            <span className="atb-block-item__index" title={`Position ${index + 1}`}>{index + 1}</span>
            <span className="atb-palette-item__icon" style={{ width: '16px', height: '16px', color: '#ae1164', opacity: 0.85, display: 'flex', alignItems: 'center' }}>
                <BlockIcon typeIdentifier={block.content_type} />
            </span>
            <span className="atb-block-item__name" title={block.name}>{block.name || block.content_type}</span>
            <span className="atb-block-item__type">{block.content_type}</span>

            {confirmDelete ? (
                <span className="atb-block-item__confirm">
                    <button className="atb-block-item__confirm-yes" onClick={onRemoveConfirm} type="button" title="Confirm remove">Delete</button>
                    <button className="atb-block-item__confirm-no" onClick={onRemoveCancel} type="button" title="Cancel">Cancel</button>
                </span>
            ) : (
                <button
                    className="atb-block-item__delete"
                    onClick={(e) => { e.stopPropagation(); onRemoveRequest(); }}
                    title="Remove from page"
                    type="button"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                </button>
            )}
        </div>
    );
};

export default DraggableBlockItem;