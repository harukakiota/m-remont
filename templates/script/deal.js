function initFileInput(dealUploadUrl, previewData) {
    $('#input-deal-files').fileinput({
        language: 'ru',
        uploadUrl: dealUploadUrl,
        theme:"explorer-fa",
        maxFileCount: 0,
        uploadAsync: false,
        overwriteInitial: false,
        previewFileIcon: '<i class="fa fa-file"></i>',
        initialPreviewAsData: true, // defaults markup  
        initialPreview: previewData.data,
        initialPreviewConfig: previewData.config,
        preferIconicPreview: true, // force thumbnails to display icons for specified file extensions
        initialPreviewShowDelete: true,
        previewFileIconSettings: { // configure your icon file extensions
            'doc': '<i class="fa fa-file-word-o text-primary"></i>',
            'xls': '<i class="fa fa-file-excel-o text-success"></i>',
            'ppt': '<i class="fa fa-file-powerpoint-o text-danger"></i>',
            'pdf': '<i class="fa fa-file-pdf-o text-danger"></i>',
            'zip': '<i class="fa fa-file-archive-o text-muted"></i>',
            'txt': '<i class="fa fa-file-text-o text-info"></i>',
            'jpg': '<i class="fa fa-file-photo-o text-danger"></i>', 
            'gif': '<i class="fa fa-file-photo-o text-muted"></i>', 
            'png': '<i class="fa fa-file-photo-o text-primary"></i>'    
        },
        previewFileExtSettings: { // configure the logic for determining icon file extensions
            'doc': function(ext) {
                return ext.match(/(doc|docx)$/i);
            },
            'xls': function(ext) {
                return ext.match(/(xls|xlsx)$/i);
            },
            'ppt': function(ext) {
                return ext.match(/(ppt|pptx)$/i);
            },
            'zip': function(ext) {
                return ext.match(/(zip|rar|tar|gzip|gz|7z)$/i);
            },
            'txt': function(ext) {
                return ext.match(/(txt|ini|csv|java|php|js|css)$/i);
            },
            'jpg': function(ext) {
                return ext.match(/(jpg|jpeg)$/i);
            },
            'gif': function(ext) {
                return ext.match(/(gif)$/i);
            },
            'png': function(ext) {
                return ext.match(/(png)$/i);
            }
        }
    });
}

$(document).ready(function () {
    // activate fileinput
    if(!$('#btn-deal-files').parent().hasClass('disabled')) {
        // decode html entities in passed data
        var textArea = document.createElement('textarea');
        textArea.innerHTML = document.twigContainer.dealFilePreview;
        filePreviewClean = textArea.value;
        textArea.remove();

        initFileInput(document.twigContainer.postFile, JSON.parse(filePreviewClean));
    }

    $('#btn-deal-data').change(function(e) {
        e.preventDefault();
        $('#content-container>.tab-pane.active').removeClass('active');
        $('#tab-deal-data').addClass('active');
    });

    $('#btn-deal-files').change(function(e) {
        e.preventDefault();
        $('#content-container>.tab-pane.active').removeClass('active');
        $('#tab-deal-files').addClass('active');
    });

    $('#btn-submit-edits').click(function (event) {
        $('#edit-deal-form').validate({
            submitHandler: function(form) {
                $.post(document.twigContainer.dealEdit,
            $('#edit-deal-form').serializeArray(),
            function(dataJson, status, jqXHR) {
                if(dataJson.success) {
                    window.location.reload();
                }
            });
        }
        });
        // event.preventDefault();
    });
    $('#btn-cancel-deal').click(function (event) {
        $.post(document.twigContainer.dealStatusPost, 
        {status: $(this).attr('data-status')},
        function(dataJson, status, jqXHR) {
            if(dataJson.success) {
                window.location.reload();
            }
        });
    });
});