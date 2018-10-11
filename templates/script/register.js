$(window).on('load', function () {
    $('#btn-registration-submit').click( function (event) {
        $("#registration-form").validate({
            rules: {
                passwordconfirm: {
                    equalTo: "#password"
                }
            },
            submitHandler: function(form) {
                $.post(document.routeContainer.register,
                    JSON.stringify($(form).serializeArray()),
                    function (dataJson, textStatus, jqXHR) {
                        var data = JSON.parse(dataJson);
                        if (data.success) {
                            $("div.alert").text(data.success).addClass('alert-success').removeClass('hidden');
                        } else if (data.error) {
                            $("div.alert").text(data.error).addClass('alert-danger').removeClass('hidden');
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
                    $("div.alert>span").html(message);
                    $("div.alert").fadeIn(150);
                    window.setTimeout(() => {
                        $("div.alert").fadeOut(1000);                        
                    }, 15000);
                } else {
                    $("div.error").hide();
                }
            }
        });        
    });

    if(document.routeContainer.redirect != "") {
        window.setTimeout(() => {
            window.location.replace(document.routeContainer.redirect);
        }, 5000);
    }
});