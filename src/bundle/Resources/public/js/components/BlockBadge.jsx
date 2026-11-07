import React from 'react';
import Icon from './Icon';

export default function BlockBadge({ block, onClose, onEdit }) {
    const { content_id, content_type, remote_id, location_id } = block.meta;

    return (
        <div className="atb-badge" style={{ top: block.top, left: block.left }}>
            <div className="atb-badge__header">
                <span className="atb-badge__type">{content_type}</span>
                <button className="atb-badge__close" onClick={onClose} aria-label="Close">×</button>
            </div>
            <div className="atb-badge__body">
                <p className="atb-badge__meta"><span>ID</span> {content_id}</p>
                <p className="atb-badge__meta"><span>Location</span> {location_id}</p>
                <p className="atb-badge__meta atb-badge__remote"><span>Remote</span> {remote_id}</p>
            </div>
            <div className="atb-badge__actions">
                <button
                    className="atb-badge__btn atb-badge__btn--edit"
                    onClick={(e) => { e.preventDefault(); onEdit(block.meta); }}
                    style={{ border: 'none', background: 'none', cursor: 'pointer', padding: 0 }}
                >
                    <Icon name="edit" /> Edit
                </button>
            </div>
        </div>
    );
}
