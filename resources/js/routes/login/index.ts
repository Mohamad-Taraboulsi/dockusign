export const store = (): string => {
    return '/login';
}

store.form = () => ({
    action: '/login',
    method: 'post' as const,
})
