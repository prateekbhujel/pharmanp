import Swal from 'sweetalert2';

export function confirmDelete({
    title = 'Delete this record?',
    content = 'This action can affect transaction history and should be used carefully.',
    confirmText = 'Yes, delete it',
    cancelText = 'Keep it',
    onOk,
}) {
    return Swal.fire({
        title,
        text: content,
        icon: 'warning',
        showCancelButton: true,
        reverseButtons: true,
        focusCancel: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        buttonsStyling: false,
        customClass: {
            popup: 'pharmanp-swal',
            title: 'pharmanp-swal-title',
            htmlContainer: 'pharmanp-swal-text',
            confirmButton: 'pharmanp-swal-confirm',
            cancelButton: 'pharmanp-swal-cancel',
            actions: 'pharmanp-swal-actions',
        },
    }).then(async (result) => {
        if (result.isConfirmed && onOk) {
            await onOk();
        }
    });
}
