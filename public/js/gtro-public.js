jQuery(document).ready(function ($) {
    console.log("GTRO Data loaded:", gtroData);
    console.log("Max Tours:", gtroData.maxTours);

    // Configuration de la sélection multiple
    const vehicleSelection = $(".gtro-vehicle-selection");
    const selectionType = vehicleSelection.data("selection-type");
    const maxVehicles = {
        single: 1,
        double: 2,
        triple: 3,
        quadruple: 4,
    }[selectionType];

    let selectedVehicles = [];

    // Gestion de la sélection des véhicules
    $(".vehicle-card")
        .off("click")
        .on("click", function () {
            const $this = $(this);
            const vehicleId = $this.data("value");
            const vehicleName = $this.find("h4").text();
            const category = $this.data("category");

            if (selectionType === "single") {
                // Comportement existant pour la sélection unique
                $(".vehicle-card").removeClass("selected");
                $this.addClass("selected");
                selectedVehicles = [
                    { id: vehicleId, name: vehicleName, category: category },
                ];
            } else {
                // Nouveau comportement pour la sélection multiple
                if ($this.hasClass("selected")) {
                    $this.removeClass("selected");
                    selectedVehicles = selectedVehicles.filter(
                        (v) => v.id !== vehicleId
                    );
                } else {
                    if (selectedVehicles.length >= maxVehicles) {
                        alert(
                            `Vous ne pouvez sélectionner que ${maxVehicles} véhicule(s) maximum.`
                        );
                        return;
                    }
                    $this.addClass("selected");
                    selectedVehicles.push({
                        id: vehicleId,
                        name: vehicleName,
                        category: category,
                    });
                }
            }

            $(".vehicle-counter span").text(selectedVehicles.length);
            updateSelectedVehiclesList();

            // Mettre à jour le champ caché
            $('select[name="gtro_vehicle"]').val(
                selectedVehicles.map((v) => v.id).join(",")
            );

            // Déclencher directement le calcul du prix
            updatePrice();
        });

    // Fonction pour mettre à jour la liste des véhicules sélectionnés
    function updateSelectedVehiclesList() {
        const $list = $(".selected-vehicles-list");
        if (!$list.length) return;

        $list.empty();
        selectedVehicles.forEach((vehicle) => {
            $list.append(`
                <div class="selected-vehicle">
                    <span>${vehicle.name}</span>
                    <button type="button" class="remove-vehicle" data-id="${vehicle.id}">×</button>
                </div>
            `);
        });
    }

    // Gestion de la suppression depuis la liste
    $(document).on("click", ".remove-vehicle", function (e) {
        e.preventDefault();
        const vehicleId = $(this).data("id");

        // Désélectionner la carte
        $(`.vehicle-card[data-value="${vehicleId}"]`).removeClass("selected");

        // Mettre à jour la liste
        selectedVehicles = selectedVehicles.filter((v) => v.id !== vehicleId);
        updateSelectedVehiclesList();

        // Mettre à jour le compteur
        $(".vehicle-counter span").text(selectedVehicles.length);

        // Mettre à jour le champ caché et déclencher le calcul du prix
        $('select[name="gtro_vehicle"]')
            .val(selectedVehicles.map((v) => v.id).join(","))
            .trigger("change");
    });

    // Créer les conteneurs de prix s'ils n'existent pas
    if ($(".gtro-total-price").length === 0) {
        $(".product .price").after('<div class="gtro-total-price"></div>');
    }
    if ($(".gtro-price-details").length === 0) {
        $(".gtro-total-price").after('<div class="gtro-price-details"></div>');
    }

    function formatPrice(price) {
        // Arrondir au nombre entier supérieur
        const roundedPrice = Math.ceil(price);

        // Formatter sans décimales
        return new Intl.NumberFormat("fr-FR", {
            style: "currency",
            currency: "EUR",
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(roundedPrice);
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
        console.log("Selected vehicles:", selectedVehicles);
        console.log(
            "Vehicle IDs:",
            selectedVehicles.map((v) => v.id)
        );
        console.log("Combos disponibles:", gtroData.combosVoitures);

        let totalPrice = parseFloat(gtroData.basePrice);
        let totalVehicleSupplementBase = 0;
        let formulePrice = 0;
        let extraLapsPrice = 0;
        let highestCategory = "1"; // Catégorie par défaut

        // Traitement des véhicules sélectionnés
        selectedVehicles.forEach((vehicle) => {
            if (gtroData.vehiclesData[vehicle.id]) {
                const vehicleData = gtroData.vehiclesData[vehicle.id];
                console.log("Processing vehicle:", {
                    id: vehicle.id,
                    data: vehicleData,
                    supplement: vehicleData.supplement_base,
                });
                totalVehicleSupplementBase += vehicleData.supplement_base || 0;
            }
        });

        // Ajouter le supplément des véhicules au prix total
        totalPrice += totalVehicleSupplementBase;

        // Traitement des formules (si pas de tours max)
        if (parseInt(gtroData.maxTours) === 0) {
            const selectedFormuleOption = $(
                'select[name="gtro_formule_option"] option:selected'
            );
            const priceFromAttribute = selectedFormuleOption.attr("data-price");
            if (priceFromAttribute) {
                formulePrice = parseFloat(priceFromAttribute);
                totalPrice += formulePrice;
            }
        }

        // Calcul des tours supplémentaires pour chaque véhicule
        if (gtroData.maxTours > 0) {
            const extraLaps =
                parseInt($('input[name="gtro_extra_laps"]').val()) || 0;
            if (extraLaps > 0) {
                // Calculer le prix des tours supplémentaires pour chaque véhicule
                selectedVehicles.forEach((vehicle) => {
                    if (gtroData.vehiclesData[vehicle.id]) {
                        const vehicleData = gtroData.vehiclesData[vehicle.id];
                        const category = vehicleData.categorie;

                        if (category && gtroData.categoryPrices[category]) {
                            const pricePerLap =
                                gtroData.categoryPrices[category];
                            const vehicleExtraLapsPrice =
                                extraLaps * pricePerLap;
                            extraLapsPrice += vehicleExtraLapsPrice;

                            console.log(
                                `Tours supplémentaires pour ${vehicle.name}:`,
                                {
                                    category: category,
                                    pricePerLap: pricePerLap,
                                    total: vehicleExtraLapsPrice,
                                }
                            );
                        }
                    }
                });

                totalPrice += extraLapsPrice;
            }
        }

        // Appliquer la remise multi-véhicules si applicable
        let multiVehicleDiscount = 0;
        if (selectedVehicles.length > 1) {
            const nombreVehicules = `${selectedVehicles.length}gt`;

            // Rechercher la remise correspondante dans les données Metabox
            if (
                gtroData.combosVoitures &&
                Array.isArray(gtroData.combosVoitures)
            ) {
                const comboFound = gtroData.combosVoitures.find(
                    (combo) => combo.type_combo === nombreVehicules
                );

                if (comboFound && comboFound.remise) {
                    const remisePercent = parseFloat(comboFound.remise);
                    multiVehicleDiscount = totalPrice * (remisePercent / 100);
                    totalPrice -= multiVehicleDiscount;
                    console.log(
                        `Remise multi-véhicules ${nombreVehicules} (${remisePercent}%):`,
                        multiVehicleDiscount
                    );
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
        console.log("Vehicle supplement:", totalVehicleSupplementBase);

        updatePriceDisplays(totalPrice, {
            basePrice: formulePrice || gtroData.basePrice,
            vehicleSupplementBase: totalVehicleSupplementBase,
            selectedCategory: highestCategory,
            extraLapsPrice,
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
