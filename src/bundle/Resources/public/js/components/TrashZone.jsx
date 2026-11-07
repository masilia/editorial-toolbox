import React from 'react';

const TrashZone = ({ draggedItem, onDrop }) => {
    const isActive = draggedItem?.type === 'block';

    const handleDragOver = (e) => {
        if (draggedItem?.type !== 'block') return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    };

    const handleDrop = (e) => {
        if (draggedItem?.type !== 'block') return;
        e.preventDefault();
        onDrop();
    };

    return (
        <div
            className={`atb-trash-zone ${isActive ? 'atb-trash-zone--active' : ''}`}
            onDragOver={handleDragOver}
            onDrop={handleDrop}
        >
            <span className="atb-trash-zone__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    <line x1="10" y1="11" x2="10" y2="17"></line>
                    <line x1="14" y1="11" x2="14" y2="17"></line>
                </svg>
            </span>
            <span className="atb-trash-zone__label">Drop to remove</span>
        </div>
    );
};

export default TrashZone;