jQuery(document).ready(function ($) {
    console.log("GTRO Data loaded:", gtroData);

    function updatePrice() {
        // Prix de base du produit
        let totalPrice = parseFloat(gtroData.basePrice);
        console.log("Base price:", totalPrice);

        // Récupérer la voiture sélectionnée
        const selectedVehicle = $(
            'select[name="gtro_vehicle"] option:selected'
        );
        const vehicleValue = selectedVehicle.val();

        // Variables pour le détail des prix
        let vehicleSupplementBase = 0;
        let extraLapsPrice = 0;
        let selectedCategory = "";

        // Ajouter le supplément de base de la voiture
        if (vehicleValue && gtroData.vehiclesData[vehicleValue]) {
            const vehicleData = gtroData.vehiclesData[vehicleValue];
            vehicleSupplementBase = vehicleData.supplement_base;
            totalPrice += vehicleSupplementBase;
            selectedCategory = vehicleData.categorie;
            console.log(
                "Added vehicle base supplement:",
                vehicleSupplementBase
            );

            // Tours supplémentaires (prix selon la catégorie de la voiture)
            const extraLaps =
                parseInt($('input[name="gtro_extra_laps"]').val()) || 0;
            if (
                extraLaps > 0 &&
                gtroData.categoryPrices[vehicleData.categorie]
            ) {
                const pricePerLap =
                    gtroData.categoryPrices[vehicleData.categorie];
                extraLapsPrice = extraLaps * pricePerLap;
                totalPrice += extraLapsPrice;
                console.log("Added extra laps price:", extraLapsPrice);
            }
        }

        // Sous-total avant promotion
        const subtotalBeforePromo = totalPrice;

        // Promotion sur date
        let promoAmount = 0;
        const selectedDate = $('select[name="gtro_date"]').val();
        if (
            selectedDate &&
            Array.isArray(gtroData.datesPromo) &&
            gtroData.datesPromo.length > 0
        ) {
            const datePromo = gtroData.datesPromo.find(
                (d) => d.date === selectedDate
            );
            if (datePromo && datePromo.promo > 0) {
                promoAmount = totalPrice * (datePromo.promo / 100);
                totalPrice -= promoAmount;
                console.log("Applied discount:", promoAmount);
            }
        }

        // Options supplémentaires
        const optionsDetails = [];
        if (gtroData.availableOptions) {
            $('input[name="gtro_options[]"]:checked').each(function () {
                const optionId = $(this).val();
                if (gtroData.availableOptions[optionId]) {
                    const optionPrice = parseFloat(
                        gtroData.availableOptions[optionId]
                    );
                    totalPrice += optionPrice;
                    optionsDetails.push({
                        label: $(this).next("label").text(),
                        price: optionPrice,
                    });
                }
            });
        }

        console.log("Final total:", totalPrice);

        // Mettre à jour tous les affichages de prix
        updatePriceDisplays(totalPrice, {
            basePrice: gtroData.basePrice,
            vehicleSupplementBase,
            selectedCategory,
            extraLapsPrice,
            subtotalBeforePromo,
            promoAmount,
            optionsDetails,
        });
    }

    function updatePriceDisplays(price, details) {
        const formattedPrice = formatPrice(price);

        // Mettre à jour le prix total GTRO
        $(".gtro-total-price").html(formattedPrice);

        // Mettre à jour le prix WooCommerce
        $(".product .price .amount").html(formattedPrice);
        $(".product .price").show();

        // Si on utilise les détails de prix
        if (gtroData.showPriceDetails) {
            updatePriceDetails(price, details);
        }
    }

    function updatePriceDetails(finalPrice, details) {
        const priceDetails = [];

        // Prix de base (basePrice + supplement)
        const baseWithSupplement =
            parseFloat(details.basePrice) +
            parseFloat(details.vehicleSupplementBase);
        priceDetails.push(
            `<p class="base-price">Prix de base: ${formatPrice(
                baseWithSupplement
            )}</p>`
        );

        // Tours supplémentaires (toujours afficher, même à 0)
        const extraLaps =
            parseInt($('input[name="gtro_extra_laps"]').val()) || 0;
        const extraLapsPrice = extraLaps > 0 ? details.extraLapsPrice : 0;
        priceDetails.push(
            `<p class="extra-laps">Tours supplémentaires (${extraLaps}): +${formatPrice(
                extraLapsPrice
            )}</p>`
        );

        // Options supplémentaires (toujours afficher, même à 0)
        const optionsTotal = details.optionsDetails.reduce(
            (sum, option) => sum + option.price,
            0
        );
        priceDetails.push(
            `<p class="options-total">Options supplémentaires: +${formatPrice(
                optionsTotal
            )}</p>`
        );

        // Sous-total
        const subtotal = baseWithSupplement + extraLapsPrice + optionsTotal;
        priceDetails.push(
            `<p class="subtotal">Sous-total: ${formatPrice(subtotal)}</p>`
        );

        // Ligne pour la promotion (invisible si pas de promo)
        const promoAmount = details.promoAmount || 0;
        const selectedDate = $('select[name="gtro_date"]').val();
        const datePromo =
            selectedDate && gtroData.datesPromo
                ? gtroData.datesPromo.find((d) => d.date === selectedDate)
                : null;

        if (datePromo && datePromo.promo > 0) {
            priceDetails.push(
                `<p class="promo">Promotion (-${
                    datePromo.promo
                }%): -${formatPrice(promoAmount)}</p>`
            );
        } else {
            priceDetails.push(`<p class="promo invisible">&nbsp;</p>`);
        }

        // Total final
        priceDetails.push(
            `<p class="total"><strong>Total: ${formatPrice(
                finalPrice
            )}</strong></p>`
        );

        // Mettre à jour l'affichage des détails
        $(".gtro-price-details").html(`
            <div class="price-details">
                ${priceDetails.join("")}
            </div>
        `);
    }

    function formatPrice(price) {
        return new Intl.NumberFormat("fr-FR", {
            style: "currency",
            currency: "EUR",
        }).format(price);
    }

    // Events avec délai pour éviter les calculs trop fréquents
    let updateTimeout;
    $(
        'select[name="gtro_vehicle"], input[name="gtro_extra_laps"], select[name="gtro_date"], input[name="gtro_options[]"]'
    ).on("change", function () {
        clearTimeout(updateTimeout);
        updateTimeout = setTimeout(updatePrice, 100);
    });

    // Créer les conteneurs de prix s'ils n'existent pas
    if ($(".gtro-total-price").length === 0) {
        $(".product .price").after('<div class="gtro-total-price"></div>');
    }
    if ($(".gtro-price-details").length === 0) {
        $(".gtro-total-price").after('<div class="gtro-price-details"></div>');
    }

    // Initial calculation
    updatePrice();
});
