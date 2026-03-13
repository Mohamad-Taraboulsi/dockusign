export const show = (): string => {
    return '/settings/two-factor';
}

export const enable = (): string => {
    return '/user/two-factor-authentication';
}

enable.form = () => ({
    action: '/user/two-factor-authentication',
    method: 'post' as const,
})

export const disable = (): string => {
    return '/user/two-factor-authentication';
}

disable.form = () => ({
    action: '/user/two-factor-authentication',
    method: 'delete' as const,
})

export const confirm = (): string => {
    return '/user/confirmed-two-factor-authentication';
}

confirm.form = () => ({
    action: '/user/confirmed-two-factor-authentication',
    method: 'post' as const,
})

export const qrCode = (): string => {
    return '/user/two-factor-qr-code';
}

export const secretKey = (): string => {
    return '/user/two-factor-secret-key';
}

export const recoveryCodes = (): string => {
    return '/user/two-factor-recovery-codes';
}

export const regenerateRecoveryCodes = (): string => {
    return '/user/two-factor-recovery-codes';
}

regenerateRecoveryCodes.form = () => ({
    action: '/user/two-factor-recovery-codes',
    method: 'post' as const,
})
