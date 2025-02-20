jQuery(document).ready(function ($) {
    function initCalendar() {
        $(".calendar-navigation button").on("click", function (e) {
            e.preventDefault();
            var year = $(this).data("year");
            console.log("Click détecté sur le bouton");
            console.log("Année:", year);

            // Ajouter un indicateur de chargement
            $(".calendar-content").addClass("loading");

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
                        // Extraire uniquement la partie calendar-content du HTML reçu
                        var newContent = $(response.data)
                            .find(".custom-calendar-year")
                            .html();

                        // Mettre à jour uniquement le contenu du calendrier
                        $(".custom-calendar-year").html(newContent);

                        // Mettre à jour l'année dans la navigation
                        $(".calendar-navigation h2").text(year);
                        $(".prev-year")
                            .data("year", year - 1)
                            .text("← " + (year - 1));
                        $(".next-year")
                            .data("year", year + 1)
                            .text(year + 1 + " →");

                        // Réinitialiser les interactions
                        initTooltipsAndHovers();
                    }
                },
                error: function (xhr, status, error) {
                    console.error("Erreur AJAX:", error);
                },
                complete: function () {
                    // Retirer l'indicateur de chargement
                    $(".calendar-content").removeClass("loading");
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
