export const request = (): string => {
    return '/forgot-password';
}

export const email = (): string => {
    return '/forgot-password';
}

email.form = () => ({
    action: '/forgot-password',
    method: 'post' as const,
})

export const update = (): string => {
    return '/reset-password';
}

update.form = () => ({
    action: '/reset-password',
    method: 'post' as const,
})
