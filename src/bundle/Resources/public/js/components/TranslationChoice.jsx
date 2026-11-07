import React, { useState, useEffect } from 'react';
import { apiUrl } from '../config';
import { apiFetch } from '../api';

/**
 * Shown before the edit form when the content has no text in the language of the site (it is displayed through
 * Ibexa's fallback): the editor decides whether to create that translation (empty, copied, or machine-translated by a
 * provider) or to edit the existing text. Nothing is created until a choice is made.
 *
 * onChoose(null)                                        -> edit the text that exists
 * onChoose({ mode: 'blank' })                           -> an empty translation
 * onChoose({ mode: 'copy', from })                      -> pre-filled with the text of `from` (what Ibexa's "Translate" does)
 * onChoose({ mode: 'provider', from, provider })        -> pre-filled by a translation provider
 */
export default function TranslationChoice({ meta, onChoose, onCancel }) {
    const [options, setOptions] = useState(null);
    const [error, setError] = useState(null);
    const [source, setSource] = useState(null);
    const [provider, setProvider] = useState('');

    useEffect(() => {
        let cancelled = false;

        apiFetch(apiUrl('translationOptions', { contentId: meta.content_id, languageCode: meta.site_language }))
            .then(async (res) => {
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.error || 'Could not load the translation options');
                return data;
            })
            .then((data) => {
                if (cancelled) return;
                setOptions(data);
                setSource(data.default_source);
                setProvider(data.providers[0]?.id ?? '');
            })
            .catch((e) => !cancelled && setError(e.message));

        return () => { cancelled = true; };
    }, [meta.content_id, meta.site_language]);

    useEffect(() => {
        const onKey = (e) => { if (e.key === 'Escape') onCancel(); };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [onCancel]);

    const target = options?.target.name ?? meta.site_language;
    const sourceName = options?.available_languages.find((l) => l.code === source)?.name ?? source;
    const available = options?.available_languages ?? [];
    const displayedName = available.find((l) => l.code === meta.current_language)?.name ?? meta.current_language;

    return (
        <div className="atb-tr" role="presentation" onMouseDown={(e) => { if (e.target === e.currentTarget) onCancel(); }}>
            <div className="atb-tr__dialog" role="dialog" aria-modal="true" aria-labelledby="atb-tr-title">
                <h2 className="atb-tr__title" id="atb-tr-title">No {target} translation yet</h2>

                {!options && !error && <p className="atb-tr__text">Loading…</p>}

                {error && (
                    <>
                        <p className="atb-tr__text atb-tr__text--error">{error}</p>
                        <div className="atb-tr__actions">
                            <button type="button" className="atb-tr__link" onClick={() => onChoose(null)}>Edit the existing text instead</button>
                            <button type="button" className="atb-tr__btn atb-tr__btn--ghost" onClick={onCancel}>Cancel</button>
                        </div>
                    </>
                )}

                {options && (
                    <>
                        <p className="atb-tr__text">
                            This content only exists in {available.map((l) => l.name).join(', ')}. The page shows it as a fallback.
                            What do you want to do?
                        </p>

                        {available.length > 1 && (
                            <label className="atb-tr__field">
                                <span>Start from the text in</span>
                                <select value={source} onChange={(e) => setSource(e.target.value)}>
                                    {available.map((l) => <option key={l.code} value={l.code}>{l.name}</option>)}
                                </select>
                            </label>
                        )}

                        <button type="button" className="atb-tr__option" onClick={() => onChoose({ mode: 'copy', from: source })}>
                            <strong>Create the {target} translation from the {sourceName} text</strong>
                            <span>Pre-filled with the existing text, like Ibexa&rsquo;s own &ldquo;Translate&rdquo;. You then edit it.</span>
                        </button>

                        <button type="button" className="atb-tr__option" onClick={() => onChoose({ mode: 'blank' })}>
                            <strong>Create an empty {target} translation</strong>
                            <span>Start with blank fields.</span>
                        </button>

                        {options.providers.length > 0 && (
                            <div className="atb-tr__option atb-tr__option--provider">
                                <strong>Translate from {sourceName} with a provider</strong>
                                <span>Machine translation, pre-filled in the form: review it before you publish.</span>
                                <div className="atb-tr__row">
                                    <select value={provider} onChange={(e) => setProvider(e.target.value)} aria-label="Translation provider">
                                        {options.providers.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                                    </select>
                                    <button
                                        type="button"
                                        className="atb-tr__btn"
                                        disabled={!provider}
                                        onClick={() => onChoose({ mode: 'provider', from: source, provider })}
                                    >
                                        Translate
                                    </button>
                                </div>
                            </div>
                        )}

                        <div className="atb-tr__actions">
                            <button type="button" className="atb-tr__link" onClick={() => onChoose(null)}>
                                Edit the existing {displayedName} text instead
                            </button>
                            <button type="button" className="atb-tr__btn atb-tr__btn--ghost" onClick={onCancel}>Cancel</button>
                        </div>
                    </>
                )}
            </div>
        </div>
    );
}
