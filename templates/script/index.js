$(document).ready(function () {
    // activate "new deal" button and tooltip
    $('#btn-new-deal').tooltip({'placement':'right'});
    $('#btn-submit-new-deal').click(function (event) {
        $('#new-deal-form').validate({
            submitHandler: function(form) {
            $.post(document.twigContainer.dealCreate,
                JSON.stringify($('#new-deal-form').serializeArray()),
                function(dataJson, status, jqXHR) {
                    if(dataJson) {
                        window.location.replace(dataJson.success);
                    }
                });
            },
            invalidHandler: function(event, validator) {
                // 'this' refers to the form
                    var errors = validator.numberOfInvalids();
                    if (errors) {
                        var message = errors == 1
                            ? 'Пожалуйста, заполните выделенное поле.'
                            : 'Пожалуйста, заполните выделенные поля.';
                        $("#new-deal-alert>span").html(message);
                        $("#new-deal-alert").fadeIn(150);
                        window.setTimeout(() => {
                            $("#new-deal-alert").fadeOut(1000);                        
                        }, 15000);
                    } else {
                        $("#new-deal-alert").hide();
                    }
                }    
        });
        // event.preventDefault();        
    });
        
    // deal category handler
    $('.btn-toggle').click(function() {
        $(this).siblings('button.btn-toggle').removeClass('btn-primary').next().slideUp(100);
        $(this).toggleClass('btn-primary').next().slideToggle(100);
    });
    // determine currently active category by the active button, toggle it
    $('.btn-dropdown-group>a.btn.btn-primary').parent().prev().click();
});
