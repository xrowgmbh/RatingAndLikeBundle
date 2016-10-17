$(function () {
    $(document).on('click', '.dcs-rating-container a', function (event) {
        event.preventDefault();
        var link = $(this);
        var style=link.attr('class');
        $.ajax(link.attr('href'))
            .done(function(data) {
                link.parent('.dcs-rating-container').html(data);
                // if class attr contains "like"
                if(style.indexOf('like') > -1) {
                    link.parent('.dcs-rating-container').('.like').attr('class', style);
                }
            });
    });
});