/**
 * Helpers for building the multipart/form-data payload submitted when
 * creating or updating a product. Kept separate so the page component
 * does not mix data-shaping logic with UI state.
 */

function appendFormValue(payload, key, value) {
    if (key === 'image_upload') {
        return;
    }

    if (value === undefined || value === null || value === '') {
        return;
    }

    if (typeof value === 'boolean') {
        payload.append(key, value ? '1' : '0');
        return;
    }

    payload.append(key, value);
}

/**
 * Convert validated Ant Design form values into a FormData object
 * suitable for the products API endpoint.
 *
 * @param {object} values - Form field values.
 * @param {string|null} method - Spoof method for PUT (e.g. 'PUT').
 * @returns {FormData}
 */
export function productPayload(values, method = null) {
    const payload = new FormData();
    Object.entries(values).forEach(([key, value]) => appendFormValue(payload, key, value));

    const image = values.image_upload?.[0]?.originFileObj;
    if (image) {
        payload.append('image', image);
    }

    if (method) {
        payload.append('_method', method);
    }

    return payload;
}

/**
 * Default field values for the Add Product form.
 * @returns {object}
 */
export function productFormDefaults() {
    return {
        is_active: true,
        is_batch_tracked: true,
        conversion: 1,
        previous_price: 0,
        discount_percent: 0,
        cc_rate: 0,
        reorder_level: 10,
        reorder_quantity: 0,
        purchase_price: 0,
        mrp: 0,
        selling_price: 0,
    };
}

/**
 * Populate the form for editing an existing product.
 *
 * @param {object} record - API product record.
 * @returns {object} - Values suitable for form.setFieldsValue().
 */
export function productEditValues(record) {
    return {
        ...record,
        company_id: record.company?.id,
        unit_id: record.unit?.id,
        division_id: record.division?.id,
        image_upload: [],
        remove_image: false,
    };
}

/**
 * Normalise Ant Design Upload file list events.
 *
 * @param {Event|Array} event
 * @returns {Array}
 */
export function normalizeFile(event) {
    return Array.isArray(event) ? event : event?.fileList;
}
