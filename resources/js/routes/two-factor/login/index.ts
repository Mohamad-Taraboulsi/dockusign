export const store = (): string => {
    return '/two-factor-challenge';
}

store.form = () => ({
    action: '/two-factor-challenge',
    method: 'post' as const,
})
