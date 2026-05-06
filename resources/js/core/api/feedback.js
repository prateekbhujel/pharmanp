import { apiErrorMessage, apiSuccessMessage, formErrors } from './http';

export function showRequestError(notification, error, fallback = 'Request failed') {
    const description = apiErrorMessage(error, fallback);

    notification.error({
        message: fallback,
        description,
    });
}

export function showRequestSuccess(notification, response, fallback = 'Saved successfully') {
    notification.success({
        message: apiSuccessMessage(response, fallback),
    });
}

export function applyRequestFormErrors(form, error) {
    const errors = formErrors(error);

    if (errors.length) {
        form.setFields(errors);
    }

    return errors;
}
