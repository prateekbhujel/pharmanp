export function can(user, permission) {
    return Boolean(user?.is_owner || user?.permissions?.includes(permission));
}
