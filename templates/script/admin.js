$(document).ready(function () {
    $('.pntr-responsive:not(.pntr-success)').click(function () {
        $.post(document.twigContainer.dealStatusPost, 
        {status: $(this).attr('data-status')}, 
        function(dataJson, status, jqXHR) {
            if(dataJson.success) {
                window.location.reload();
            }
        });
    });
    
    $('#btn-delete-deal').click(function () {
        $.post(document.twigContainer.dealDelete, 
        "", 
        function(dataJson, status, jqXHR) {
            window.location.replace(dataJson.target);
        });
    });
});