$(window).on('load', function () {
    $('#btn-full-statistics').click(function(e) {
        $.post(document.twigContainer.statistics, {}, function (data, status, jqXHR) {});
        e.preventDefault();        
    });
});
