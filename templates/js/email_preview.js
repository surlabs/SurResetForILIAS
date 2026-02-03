function initEmailPreview(id) {
    let $textarea = $("#" + id);

    // Crear el contenedor de preview si no existe
    if ($("#email-preview-" + id).length === 0) {
        var previewHtml = `
            <div id="email-preview-${id}" class="il-email-preview" style="
                margin-top: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                background: #fff;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            ">
                <div id="email-content-${id}" class="il-email-content" style="
                    padding: 15px;
                    min-height: 100px;
                    font-family: Arial, sans-serif;
                    line-height: 1.5;
                    color: #333;
                    white-space: pre-wrap;
                    word-wrap: break-word;
                "></div>
            </div>
        `;

        $textarea.after(previewHtml);
    }

    function replacePlaceholders(text) {
        var placeholders = {
            '[login]': '[login]',
            '[name]': '[name]',
            '[firstname]': '[firstname]',
            '[lastname]': '[lastname]',
            '[date]': '[date]',
            '[time]': '[time]',
        };

        var result = text;
        for (var placeholder in placeholders) {
            var regex = new RegExp(placeholder.replace(/[\[\]]/g, '\\$&'), 'gi');
            result = result.replace(regex, `<strong>${placeholders[placeholder]}</strong>`);
        }

        return result;
    }

    function updatePreview() {
        var content = $textarea.val();
        if (content.trim() === '') {
            content = '<em style="color: #999;"></em>';
        } else {
            content = replacePlaceholders(content);
        }
        $("#email-content-" + id).html(content);
    }

    $textarea.on('input keyup paste', function() {
        updatePreview();
    });

    updatePreview();
}

function initRunConfirmation(id) {
    $("#" + id).on('click', async function() {
        const url = $(this).attr('url');
        const notify = $(".notification_manual").val() || '';
        const subject = $(".notification_subject").val() || '';

        try {
            if (notify.trim().length === 0 && subject.trim().length === 0) {
                window.location.href = url;
            } else {
                const formData = new FormData();
                formData.append('notification_manual', notify.trim());
                formData.append('notification_subject', subject.trim());

                const response = await fetch(url.replace("confirmRunSchedule", "sendNotification"), {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (response.ok) {
                    window.location.href = url;
                } else {
                    console.error('Request failed:', response.statusText);
                }
            }
        } catch (error) {
            console.error('Error in run confirmation:', error);
        }
    });
}