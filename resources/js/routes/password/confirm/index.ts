export const store = (): string => {
    return '/user/confirm-password';
}

store.form = () => ({
    action: '/user/confirm-password',
    method: 'post' as const,
})
