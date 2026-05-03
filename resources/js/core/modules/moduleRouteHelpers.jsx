import React from 'react';
import { appUrl } from '../utils/url';

export function lazyNamed(importer, exportName) {
    return React.lazy(() => importer().then((module) => ({ default: module[exportName] })));
}

export function appRoutes(paths, component) {
    return Object.fromEntries(paths.map((path) => [appUrl(path), component]));
}
