jQuery(document).ready(function ($) {
    function initCalendar() {
        $(".calendar-navigation button").on("click", function (e) {
            e.preventDefault();
            var year = $(this).data("year");
            console.log("Click détecté sur le bouton");
            console.log("Année:", year);

            $.ajax({
                url: gtroAjax.ajaxurl,
                type: "POST",
                data: {
                    action: "load_calendar",
                    year: year,
                    nonce: gtroAjax.nonce,
                },
                success: function (response) {
                    console.log("Réponse reçue:", response);
                    if (response.success) {
                        $(".custom-calendar-year").replaceWith(response.data);
                        initCalendar(); // Réinitialiser les événements
                        initTooltipsAndHovers(); // Réinitialiser les tooltips après le chargement
                    }
                },
                error: function (xhr, status, error) {
                    console.error("Erreur AJAX:", error);
                },
            });
        });
        initTooltipsAndHovers();
    }

    function initTooltipsAndHovers() {
        // Interactions au survol des cellules
        $(".custom-calendar-year .calendar-cell").hover(
            function () {
                $(this).addClass("hover");
            },
            function () {
                $(this).removeClass("hover");
            }
        );

        // Tooltips améliorés pour les événements
        $(".custom-calendar-year .event-dot")
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
    }

    // Initialiser le calendrier
    initCalendar();
});
