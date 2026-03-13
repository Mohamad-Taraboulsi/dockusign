export const send = (): string => {
    return '/email/verification-notification';
}

send.form = () => ({
    action: '/email/verification-notification',
    method: 'post' as const,
})
