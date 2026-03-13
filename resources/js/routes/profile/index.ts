export const edit = (): string => {
    return '/settings/profile';
}

export const update = (): string => {
    return '/settings/profile';
}

update.form = () => ({
    action: '/settings/profile',
    method: 'patch' as const,
})

export const destroy = (): string => {
    return '/settings/profile';
}

destroy.form = () => ({
    action: '/settings/profile',
    method: 'delete' as const,
})
