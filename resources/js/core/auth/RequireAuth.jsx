import React from 'react';
import { useAuth } from './AuthProvider';

export function RequireAuth({ children }) {
    const { user } = useAuth();

    return user ? children : null;
}
