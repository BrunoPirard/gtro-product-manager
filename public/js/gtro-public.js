jQuery(document).ready(function ($) {
    /*$('select[name="gtro_formule_option"]').on("change", function () {
        const selectedOption = $(this).find("option:selected");
        console.log("=== FORMULE CHANGE DEBUG ===");
        console.log("Selected option:", selectedOption.text());
        console.log("Data price attribute:", selectedOption.attr("data-price"));
        console.log("Option value:", selectedOption.val());
        console.log("Raw option element:", selectedOption[0]);
        console.log("========================");
    });*/

    console.log("GTRO Data loaded:", gtroData);
    console.log("Max Tours:", gtroData.maxTours);
    // Gestion de la sélection des véhicules
    $(".vehicle-card").on("click", function () {
        const value = $(this).data("value");

        // Mettre à jour la sélection visuelle
        $(".vehicle-card").removeClass("selected");
        $(this).addClass("selected");

        // Mettre à jour le select caché
        $('select[name="gtro_vehicle"]').val(value).trigger("change");
    });

    // Créer les conteneurs de prix s'ils n'existent pas
    if ($(".gtro-total-price").length === 0) {
        $(".product .price").after('<div class="gtro-total-price"></div>');
    }
    if ($(".gtro-price-details").length === 0) {
        $(".gtro-total-price").after('<div class="gtro-price-details"></div>');
    }

    function formatPrice(price) {
        return new Intl.NumberFormat("fr-FR", {
            style: "currency",
            currency: "EUR",
        }).format(price);
    }

    function updatePriceDisplays(price, details) {
        console.log("Updating price displays with:", price);
        console.log("Price details:", details);

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

        // Prix de base (toujours afficher le prix de base du produit)
        priceDetails.push(
            `<p class="base-price">Prix de base: ${formatPrice(
                parseFloat(details.basePrice)
            )}</p>`
        );

        // Supplément véhicule si présent
        if (details.vehicleSupplementBase > 0) {
            priceDetails.push(
                `<p class="vehicle-supplement">Supplément véhicule: +${formatPrice(
                    details.vehicleSupplementBase
                )}</p>`
            );
        }

        // Afficher soit les tours supplémentaires, soit le supplément de formule
        if (parseInt(gtroData.maxTours) > 0) {
            const extraLaps =
                parseInt($('input[name="gtro_extra_laps"]').val()) || 0;
            if (extraLaps > 0) {
                priceDetails.push(
                    `<p class="extra-laps">Tours supplémentaires (${extraLaps}): +${formatPrice(
                        details.extraLapsPrice
                    )}</p>`
                );
            }
        } else if (details.formulePrice > 0) {
            priceDetails.push(
                `<p class="formule">Supplément durée: +${formatPrice(
                    details.formulePrice
                )}</p>`
            );
        }

        // Sous-total avant promo
        priceDetails.push(
            `<p class="subtotal">Sous-total: ${formatPrice(
                details.subtotalBeforePromo
            )}</p>`
        );

        // Promotion
        const selectedDate = $('select[name="gtro_date"]').val();
        const datePromo =
            selectedDate && gtroData.datesPromo
                ? gtroData.datesPromo.find((d) => d.date === selectedDate)
                : null;
        if (datePromo && datePromo.promo > 0) {
            priceDetails.push(
                `<p class="promo">Promotion (-${
                    datePromo.promo
                }%): -${formatPrice(details.promoAmount)}</p>`
            );
        }

        // Options supplémentaires
        const optionsTotal = details.optionsDetails.reduce(
            (sum, option) => sum + option.price,
            0
        );
        if (optionsTotal > 0) {
            priceDetails.push(
                `<p class="options-total">Options supplémentaires: +${formatPrice(
                    optionsTotal
                )}</p>`
            );
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

    function updatePrice() {
        console.log("Full vehicles data:", gtroData.vehiclesData);
        // On commence avec le prix de base du produit
        let totalPrice = parseFloat(gtroData.basePrice);
        let vehicleSupplementBase = 0;
        let formulePrice = 0;
        let selectedCategory = "";
        let extraLapsPrice = 0; // Ajout de cette ligne

        console.log("Starting with base price:", totalPrice);

        // Traitement des formules (si pas de tours max)
        if (parseInt(gtroData.maxTours) === 0) {
            const selectedFormuleOption = $(
                'select[name="gtro_formule_option"] option:selected'
            );
            const priceFromAttribute = selectedFormuleOption.attr("data-price");

            console.log("=== FORMULE PROCESSING ===");
            console.log("Selected formule:", selectedFormuleOption.text());
            console.log("Formule supplement:", priceFromAttribute);

            if (priceFromAttribute) {
                formulePrice = parseFloat(priceFromAttribute);
                totalPrice += formulePrice; // Ajouter le supplément au prix de base
                console.log(
                    "Adding formule supplement. New total:",
                    totalPrice
                );
            }
        }

        // Récupérer la voiture sélectionnée
        const selectedVehicle = $(
            'select[name="gtro_vehicle"] option:selected'
        );
        const vehicleValue = selectedVehicle.val();
        console.log("Selected vehicle:", vehicleValue);

        // Ajouter le supplément de base de la voiture
        if (vehicleValue && gtroData.vehiclesData[vehicleValue]) {
            const vehicleData = gtroData.vehiclesData[vehicleValue];
            console.log("Selected vehicle data:", {
                value: vehicleValue,
                data: vehicleData,
                category: vehicleData.categorie,
                supplement: vehicleData.supplement_base,
            });

            // Vérifier que nous avons bien les données du véhicule
            if (!vehicleData.categorie) {
                console.warn("Missing category for vehicle:", vehicleValue);
            }

            vehicleSupplementBase = vehicleData.supplement_base || 0;
            selectedCategory = vehicleData.categorie || "";
            totalPrice += vehicleSupplementBase;

            console.log("Vehicle category:", selectedCategory);
            console.log("Vehicle supplement:", vehicleSupplementBase);

            // Si max_tours > 0, calculer les tours supplémentaires
            if (gtroData.maxTours > 0) {
                console.log("Processing extra laps mode");
                const extraLaps =
                    parseInt($('input[name="gtro_extra_laps"]').val()) || 0;
                console.log("Number of extra laps:", extraLaps);
                console.log("Vehicle category:", selectedCategory);
                console.log("All category prices:", gtroData.categoryPrices);

                if (
                    extraLaps > 0 &&
                    selectedCategory &&
                    gtroData.categoryPrices[selectedCategory]
                ) {
                    const pricePerLap =
                        gtroData.categoryPrices[selectedCategory];
                    console.log("Price per lap for category:", pricePerLap);
                    extraLapsPrice = extraLaps * pricePerLap;
                    totalPrice += extraLapsPrice;
                    console.log("Total extra laps price:", extraLapsPrice);
                } else if (extraLaps > 0) {
                    console.warn("Could not calculate extra laps price:", {
                        extraLaps,
                        selectedCategory,
                        categoryPrices: gtroData.categoryPrices,
                    });
                }
            }
        }

        // Sous-total avant promotion
        const subtotalBeforePromo = totalPrice;
        console.log("Subtotal before promo:", subtotalBeforePromo);

        // Promotion sur date
        let promoAmount = 0;
        const selectedDate = $('select[name="gtro_date"]').val();
        console.log("Selected date:", selectedDate);

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
                console.log("Promo percentage:", datePromo.promo);
                console.log("Promo amount:", promoAmount);
            }
        }

        // Options supplémentaires (après la promo)
        const optionsDetails = [];
        if (gtroData.availableOptions) {
            console.log("Processing additional options");
            $('input[name="gtro_options[]"]:checked').each(function () {
                const optionId = $(this).val();
                if (gtroData.availableOptions[optionId]) {
                    const optionPrice = parseFloat(
                        gtroData.availableOptions[optionId]
                    );
                    totalPrice += optionPrice;
                    optionsDetails.push({
                        label: $(this).parent().text().trim(),
                        price: optionPrice,
                    });
                    console.log(
                        "Added option:",
                        optionId,
                        "price:",
                        optionPrice
                    );
                }
            });
        }

        console.log("=== FINAL PRICE DETAILS ===");
        console.log("Total price:", totalPrice);
        console.log("Formule price:", formulePrice);
        console.log("Vehicle supplement:", vehicleSupplementBase);

        updatePriceDisplays(totalPrice, {
            basePrice: formulePrice || gtroData.basePrice,
            vehicleSupplementBase,
            selectedCategory,
            extraLapsPrice, // Cette variable est maintenant définie
            formulePrice,
            subtotalBeforePromo: totalPrice,
            promoAmount,
            optionsDetails,
        });
    }

    // Events avec délai pour éviter les calculs trop fréquents
    let updateTimeout;
    $(
        'select[name="gtro_vehicle"], input[name="gtro_extra_laps"], select[name="gtro_date"], input[name="gtro_options[]"], select[name="gtro_formule_option"]'
    ).on("change", function () {
        console.log("Change event triggered on:", this.name);
        clearTimeout(updateTimeout);
        updateTimeout = setTimeout(updatePrice, 100);
    });

    // Renommer "Options disponibles" en "Durées disponibles"
    $(".gtro-formule-options h3").text("Durées disponibles");

    // Initial calculation
    updatePrice();
});
