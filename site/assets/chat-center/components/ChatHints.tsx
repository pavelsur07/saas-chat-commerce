// assets/chat-center/components/ChatHints.tsx
import React, { useMemo, useRef, useState } from "react";

export type Suggestion = { id: string; text: string };

type Props = {
    /** Вставить текст в поле ввода (НЕ отправлять) */
    onInsert: (text: string) => void;
    /** Лоадер подсказок из API: должен вернуть массив {id, text} */
    loadSuggestions: () => Promise<Suggestion[]>;
    className?: string;
    buttonClassName?: string;
    pillClassName?: string;
};

const ChatHints: React.FC<Props> = ({
                                        onInsert,
                                        loadSuggestions,
                                        className,
                                        buttonClassName,
                                        pillClassName,
                                    }) => {
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [items, setItems] = useState<Suggestion[] | null>(null);
    const loadedOnce = useRef(false);

    const fallback = useMemo<Suggestion[]>(
        () => [
            { id: "s1", text: "Есть ли размеры S–XL?" },
            { id: "s2", text: "Сроки и стоимость доставки?" },
            { id: "s3", text: "Можно фото/видео на модели?" },
            { id: "s4", text: "Есть ли промокод для первой покупки?" },
        ],
        []
    );

    const fetchData = async () => {
        try {
            setLoading(true);
            setError(null);
            const list = await loadSuggestions();
            setItems(Array.isArray(list) ? list : []);
        } catch (e: any) {
            setError(e?.message || "Не удалось загрузить подсказки");
            setItems(fallback); // мягкий фолбэк
        } finally {
            setLoading(false);
        }
    };

    const toggle = async () => {
        const next = !open;
        setOpen(next);
        if (next && !loadedOnce.current) {
            loadedOnce.current = true;
            await fetchData();
        }
    };

    return (
        <div className={"w-full " + (className || "")} data-testid="chat-hints">
            <button
                type="button"
                onClick={toggle}
                disabled={loading}
                className={
                    "inline-flex items-center gap-2 rounded-2xl px-3 py-1.5 text-sm shadow-sm border border-slate-200 bg-white hover:bg-slate-50 " +
                    (buttonClassName || "")
                }
                aria-expanded={open}
            >
                <span aria-hidden>✨</span>
                <span>Подсказки</span>
            </button>

            {open && (
                <div className="mt-2">
                    {loading && (
                        <div className="flex items-center gap-2 text-sm opacity-80" role="status">
                            <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24" aria-hidden>
                                <circle cx="12" cy="12" r="10" stroke="currentColor" fill="none" strokeWidth="4" opacity="0.25" />
                                <path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" strokeWidth="4" />
                            </svg>
                            <span>Загрузка…</span>
                        </div>
                    )}

                    {!!error && !loading && (
                        <div className="text-sm text-red-500" role="alert">
                            {error}
                            <button type="button" onClick={fetchData} className="ml-2 underline">
                                Повторить
                            </button>
                        </div>
                    )}

                    {!loading && items && items.length === 0 && (
                        <div className="text-sm opacity-60">Подсказок пока нет</div>
                    )}

                    {!loading && items && items.length > 0 && (
                        <div className="flex flex-wrap gap-2" data-testid="pills">
                            {items.map((s) => (
                                <button
                                    type="button"
                                    key={s.id}
                                    onClick={() => onInsert(s.text)}
                                    // длинные тексты: перенос слов и строк
                                    className={
                                        "max-w-full whitespace-normal break-words rounded-full border border-slate-200 bg-slate-50 px-3 py-2 text-sm leading-snug hover:bg-slate-100 " +
                                        (pillClassName || "")
                                    }
                                    title={s.text}
                                >
                                    {s.text}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};

export default ChatHints;
