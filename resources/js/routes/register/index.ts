export const store = (): string => {
    return '/register';
}

store.form = () => ({
    action: '/register',
    method: 'post' as const,
})
