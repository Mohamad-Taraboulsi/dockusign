export const edit = (): string => {
    return '/settings/password';
}

export const update = (): string => {
    return '/settings/password';
}

update.form = () => ({
    action: '/settings/password',
    method: 'put' as const,
})
