import React, { createContext, useContext, useState, useEffect } from 'react';

export const ThemeContext = createContext({
    colorPrimary: '#0891b2',
    setColorPrimary: () => {},
});

function adjustColor(color, amount) {
    return '#' + color.replace(/^#/, '').replace(/../g, color => ('0'+Math.min(255, Math.max(0, parseInt(color, 16) + amount)).toString(16)).substr(-2));
}

function hexToRgb(hex) {
    const h = hex.startsWith('#') ? hex.slice(1) : hex;
    const r = parseInt(h.slice(0, 2), 16);
    const g = parseInt(h.slice(2, 4), 16);
    const b = parseInt(h.slice(4, 6), 16);
    return `${r}, ${g}, ${b}`;
}

export function ThemeProvider({ children }) {
    const [colorPrimary, setColorPrimary] = useState(localStorage.getItem('pharmanp-theme-color') || '#0891b2');

    useEffect(() => {
        localStorage.setItem('pharmanp-theme-color', colorPrimary);
        document.documentElement.style.setProperty('--primary-color', colorPrimary);
        document.documentElement.style.setProperty('--primary-color-rgb', hexToRgb(colorPrimary));
        // Create a slightly darker shade for gradients
        document.documentElement.style.setProperty('--primary-color-dark', adjustColor(colorPrimary, -40));
    }, [colorPrimary]);

    return (
        <ThemeContext.Provider value={{ colorPrimary, setColorPrimary }}>
            {children}
        </ThemeContext.Provider>
    );
}

export function useTheme() {
    return useContext(ThemeContext);
}
