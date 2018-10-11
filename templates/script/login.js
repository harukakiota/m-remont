$(window).on('load', function () {
    $("#signin-form").submit(function (event) {
        $.post(document.routeContainer.auth,
            JSON.stringify($("#signin-form").serializeArray()),
            function (dataJson, textStatus, jqXHR) {
                // var data = JSON.parse(dataJson);
                var data = dataJson.data;
                if (data.success) {
                    window.location.replace(document.routeContainer.home);
                } else if (data.error) {
                    $("#signin-alert>p").text(data.error);
                    $("#signin-alert").removeClass('hidden');
                }
            });
        event.preventDefault();
    });

    $('#bth-toggle-form').click(function (event) {
        if($("#register-container").css('display') == 'none') {
            $("#signin-container").slideUp(100);
            $("#register-container").slideDown(100);
            $(this).text("Войти");
        } else {
            $("#register-container").slideUp(100);
            $("#signin-container").slideDown(100);            
            $(this).text("Зарегистрироваться");
        }
    });

    $("#register-form").submit(function (event) {
        $.post(document.routeContainer.register,
            JSON.stringify($("#register-form").serializeArray()),
            function (data, textStatus, jqXHR) {
                // var data = JSON.parse(dataJson);
                if (data.success) {
                    $("#register-alert>p").text(data.success);
                    $("#register-alert").addClass('alert-success').removeClass('hidden');
                } else if (data.error) {
                    $("#register-alert>p").text(data.error);
                    $("#register-alert").addClass('alert-danger').removeClass('hidden');
                }
            });
        event.preventDefault();
    });
});