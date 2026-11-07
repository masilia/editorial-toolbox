import React from 'react';
import BlockIcon from './BlockIcon';

const BlockPaletteItem = ({ typeIdentifier, typeName, onDragStart, onDragEnd, onAdd }) => {
    const handleDragStart = (e) => {
        e.dataTransfer.setData('text/plain', typeIdentifier);
        e.dataTransfer.effectAllowed = 'copy';
        onDragStart();
    };

    const displayName = typeName || typeIdentifier.replace(/_/g, ' ').replace(/-/g, ' ');

    return (
        <div
            className="atb-palette-item"
            draggable
            onDragStart={handleDragStart}
            onDragEnd={onDragEnd}
            data-palette-item={typeIdentifier}
        >
            <span className="atb-palette-item__icon">
                <BlockIcon typeIdentifier={typeIdentifier} />
            </span>
            <span className="atb-palette-item__name">{displayName}</span>
            <button
                className="atb-palette-item__add"
                onClick={(e) => { e.stopPropagation(); onAdd(); }}
                type="button"
                title={`Add ${displayName}`}
            >+</button>
        </div>
    );
};

export default BlockPaletteItem;