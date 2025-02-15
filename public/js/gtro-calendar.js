jQuery(document).ready(function ($) {
    // Fonction pour initialiser le calendrier
    function initCalendar() {
        $(".custom-calendar").each(function () {
            // Ajouter des interactions au survol
            $(".calendar-cell").hover(
                function () {
                    $(this).addClass("hover");
                },
                function () {
                    $(this).removeClass("hover");
                }
            );

            // Ajouter des tooltips améliorés pour les événements
            $(".event-dot")
                .hover(
                    function () {
                        var title = $(this).attr("title");
                        $(this).data("tipText", title).removeAttr("title");
                        $('<p class="tooltip"></p>')
                            .text(title)
                            .appendTo("body")
                            .fadeIn("slow");
                    },
                    function () {
                        $(this).attr("title", $(this).data("tipText"));
                        $(".tooltip").remove();
                    }
                )
                .mousemove(function (e) {
                    var mouseX = e.pageX + 20;
                    var mouseY = e.pageY + 10;
                    $(".tooltip").css({ top: mouseY, left: mouseX });
                });
        });
    }

    // Initialiser le calendrier
    initCalendar();
});
