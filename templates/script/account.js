$(document).ready(function () {
    $('#btn-account-submit').click(function (e) {
        $("#account-form").validate({
            rules: {
                passwordconfirm: {
                    equalTo: "#password"
                }
            },
            submitHandler: function (form) {
                $.post($(form).attr('action'), 
                    JSON.stringify($(form).serializeArray()),
                    function(data, status, jqXHR) {
                        if(data.success) {
                            $("div.alert.alert-account").text(data.success).addClass('alert-success').removeClass('hidden');
                        } else if (data.error) {
                            $("div.alert.alert-account").text(data.error).addClass('alert-danger').removeClass('hidden');
                        }
                        window.setTimeout( () => {
                            $("div.alert.alert-account").fadeOut(600);
                        }, 15000);
                });
            }
        });
        // e.preventDefault();
    });
});