export enum Channel {
    TELEGRAM  = 'telegram',
    WHATSAPP  = 'whatsapp',
    INSTAGRAM = 'instagram',
    AVITO     = 'avito',
    WEB       = 'web',
    SYSTEM    = 'system',
}

export const ALL_CHANNELS: Channel[] = [
    Channel.TELEGRAM,
    Channel.WHATSAPP,
    Channel.INSTAGRAM,
    Channel.AVITO,
    Channel.WEB,
    Channel.SYSTEM,
];

export function isChannel(v: unknown): v is Channel {
    return typeof v === 'string' && (ALL_CHANNELS as string[]).includes(v);
}

export function channelLabel(ch: Channel): string {
    switch (ch) {
        case Channel.TELEGRAM:  return 'Telegram';
        case Channel.WHATSAPP:  return 'WhatsApp';
        case Channel.INSTAGRAM: return 'Instagram';
        case Channel.AVITO:     return 'Avito';
        case Channel.WEB:       return 'Web-чат';
        case Channel.SYSTEM:    return 'Система';
    }
}
