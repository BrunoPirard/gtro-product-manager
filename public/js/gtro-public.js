jQuery(document).ready(function ($) {
    console.log("GTRO Data loaded:", gtroData);

    function updatePrice() {
        // Prix de base
        let totalPrice = parseFloat(gtroData.basePrice);
        console.log("Base price:", totalPrice);

        // Supplément catégorie
        const selectedVehicle = $(
            'select[name="gtro_vehicle"] option:selected'
        );
        const category = selectedVehicle.data("category");
        console.log("Selected category:", category);
        console.log("Category supplements:", gtroData.categorySupplements);
        console.log(
            "Supplement for category:",
            gtroData.categorySupplements[category]
        );

        if (category && gtroData.categorySupplements[category]) {
            const categorySupp = parseFloat(
                gtroData.categorySupplements[category]
            );
            totalPrice += categorySupp;
            console.log("Added category supplement:", categorySupp);
            console.log("New total after category:", totalPrice);
        }

        // Tours supplémentaires
        const extraLaps =
            parseInt($('input[name="gtro_extra_laps"]').val()) || 0;
        if (
            extraLaps > 0 &&
            gtroData.pricePerLap &&
            extraLaps <= gtroData.maxTours
        ) {
            const lapsPrice = extraLaps * parseFloat(gtroData.pricePerLap);
            totalPrice += lapsPrice;
            console.log("Added extra laps price:", lapsPrice);
        }

        // Promotion sur date
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
                const discount = totalPrice * (datePromo.promo / 100);
                totalPrice -= discount;
                console.log("Applied discount:", discount);
            }
        }

        // Options supplémentaires
        if (gtroData.availableOptions) {
            $('input[name="gtro_options[]"]:checked').each(function () {
                const optionId = $(this).val();
                if (gtroData.availableOptions[optionId]) {
                    totalPrice += parseFloat(
                        gtroData.availableOptions[optionId]
                    );
                    console.log(
                        "Added option price:",
                        gtroData.availableOptions[optionId]
                    );
                }
            });
        }

        console.log("Final total:", totalPrice);

        // Mettre à jour tous les affichages de prix
        updatePriceDisplays(totalPrice);
    }

    function updatePriceDisplays(price) {
        const formattedPrice = formatPrice(price);

        // Mettre à jour le prix total GTRO
        $(".gtro-total-price").html(formattedPrice);

        // Mettre à jour le prix WooCommerce
        $(".product .price .amount").html(formattedPrice);
        $(".product .price").show();

        // Si on utilise les détails de prix
        if (gtroData.showPriceDetails) {
            updatePriceDetails(price);
        }
    }

    function updatePriceDetails(finalPrice) {
        const details = [];

        // Prix de base
        details.push(
            `<p>Prix de base: ${formatPrice(
                parseFloat(gtroData.basePrice)
            )}</p>`
        );

        // Supplément catégorie
        const selectedVehicle = $(
            'select[name="gtro_vehicle"] option:selected'
        );
        const category = selectedVehicle.data("category");
        if (category && gtroData.categorySupplements[category]) {
            const suppPrice = parseFloat(
                gtroData.categorySupplements[category]
            );
            if (suppPrice > 0) {
                details.push(
                    `<p>Supplément catégorie ${category}: +${formatPrice(
                        suppPrice
                    )}</p>`
                );
            }
        }

        // Tours supplémentaires
        const extraLaps =
            parseInt($('input[name="gtro_extra_laps"]').val()) || 0;
        if (extraLaps > 0) {
            const lapsPrice = extraLaps * parseFloat(gtroData.pricePerLap);
            details.push(
                `<p>Tours supplémentaires (${extraLaps}): +${formatPrice(
                    lapsPrice
                )}</p>`
            );
        }

        // Sous-total avant remise
        const subtotal =
            parseFloat(gtroData.basePrice) +
            (category
                ? parseFloat(gtroData.categorySupplements[category] || 0)
                : 0) +
            extraLaps * parseFloat(gtroData.pricePerLap);
        details.push(`<p>Sous-total: ${formatPrice(subtotal)}</p>`);

        // Promotion
        const selectedDate = $('select[name="gtro_date"]').val();
        if (selectedDate && gtroData.datesPromo.length > 0) {
            const datePromo = gtroData.datesPromo.find(
                (d) => d.date === selectedDate
            );
            if (datePromo && datePromo.promo > 0) {
                const discount = subtotal * (datePromo.promo / 100);
                details.push(
                    `<p>Promotion (-${datePromo.promo}%): -${formatPrice(
                        discount
                    )}</p>`
                );
            }
        }

        // Options supplémentaires
        $('input[name="gtro_options[]"]:checked').each(function () {
            const optionId = $(this).val();
            const optionLabel = $(this).next("label").text();
            if (gtroData.availableOptions[optionId]) {
                details.push(
                    `<p>${optionLabel}: +${formatPrice(
                        parseFloat(gtroData.availableOptions[optionId])
                    )}</p>`
                );
            }
        });

        // Total final
        details.push(
            `<p class="total"><strong>Total: ${formatPrice(
                finalPrice
            )}</strong></p>`
        );

        // Mettre à jour l'affichage des détails
        $(".gtro-price-details").html(`
            <div class="price-details">
                ${details.join("")}
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
