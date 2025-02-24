jQuery(document).ready(
    function ($) {
        // Gestion de l'ajout de dates promotionnelles
        $("#add-promo-date").on(
            "click", function () {
                var index = $(".promo-date-row").length;
                var newRow = `
                <div class="promo-date-row">
                <input type="date" name="gtro_promo_dates[${index}][date]">
                <input type="number" name="gtro_promo_dates[${index}][discount]"
                       min="0" max="100" placeholder="Réduction en %">
                <button type="button" class="button remove-promo-date">×</button>
                </div>
                `;
                $("#gtro-promo-dates-container").append(newRow);
            }
        );

        // Gestion de la suppression de dates promotionnelles
        $(document).on(
            "click", ".remove-promo-date", function () {
                $(this).closest(".promo-date-row").remove();
            }
        );

        // Gestion de l'affichage conditionnel du champ formule
        var maxToursField = $("#_gtro_max_tours");
        var formuleField = $("#_gtro_formule").closest(".form-field");

        function toggleFormuleField()
        {
            if (maxToursField.val() === "0") {
                formuleField.show();
            } else {
                formuleField.hide();
                $("#_gtro_formule").val("");
            }
        }

        maxToursField.on("change", toggleFormuleField);
        toggleFormuleField(); // État initial
    }
);
