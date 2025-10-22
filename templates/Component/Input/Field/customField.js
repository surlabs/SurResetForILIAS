$(document).ready(function() {
    $(".objectSelectorSelect").click(function () {
        const il_signal = $(this).attr("modal-signal");

        $(this).trigger(il_signal,
            {
                'id' : il_signal, 'event' : 'click',
                'triggerer' : $(this),
                'options' : JSON.parse('[]')
            }
        );
    });

    $(".objectSelectorReset").click(function () {
        const objectSelector = $(this).parent().attr("objectSelector");
        const $cont = $("#" + objectSelector + "_cont");

        $cont.find(".objectSelectorResult").val(JSON.stringify([])).trigger("input");
        $cont.find(".obj-node").prop("checked", false);
    });

    $(".objectSelectorResult").on("input", function () {
        const objectSelector = $(this).parent().attr("objectSelector");
        try {
            const value = JSON.parse($(this).val());

            let listHtml = "<ul>";

            if (value) {
                for (let i = 0; i < value.length; i++) {
                    listHtml += "<li>" + value[i].title + "</li>";
                }
            }

            listHtml += "</ul>";

            $("#" + objectSelector + "_cont_txt").html(listHtml);
        } catch (e) {
            console.log("Invalid JSON: " + e);
        }
    });


    $(document).on("change", ".obj-node", function() {
        const objectSelector = $(this).attr("objectSelector-id");

        const value = $(this).is(":checked");
        const $result = $("#" + objectSelector + "_cont").find(".objectSelectorResult");
        let result = $result.val();

        try {
            result = JSON.parse(result);
        } catch (e) {
            result = [];
        }

        result = result.filter(function (item) {
            return parseInt(item.id) !== parseInt($(this).attr("node-id"));
        }.bind(this));

        if (value) {
            result.push({
                id: parseInt($(this).attr("node-id")),
                title: $(this).attr("node-title")
            });
        }

        result.sort(function (a, b) {
            return a.id - b.id;
        });

        $result.val(JSON.stringify(result)).trigger("input");
    });

    new MutationObserver((mutations, obs) => {
        $(".ilSurResetTreeNode").each(function() {
            const objectSelector = $(".objectSelectorResult").parent().attr("objectSelector");
            const nodeId = $(this).attr("data-id");
            const nodeTitle = $(this).find(".c-tree__node__label").text().trim();


            if ($(this).find(".obj-node[objectSelector-id='" + objectSelector + "'][node-id='" + nodeId + "']").length === 0) {
                $(this).prepend(
                    '<input type="checkbox" class="obj-node" objectSelector-id="' + objectSelector + '" node-id="' + nodeId + '" node-title="' + nodeTitle + '">'
                );
            }
        });

        if ($(".obj-node").length > 0) {
            obs.disconnect();

            setTimeout(function() {
                $(".objectSelectorResult").each(function() {
                    const objectSelector = $(this).parent().attr("objectSelector");

                    try {
                        const value = JSON.parse($(this).val());

                        if (value) {
                            $(this).val("");
                            for (let i = 0; i < value.length; i++) {
                                const node = $("#" + objectSelector + "_cont").find(".obj-node[objectSelector-id='" + objectSelector + "'][node-id='" + value[i].id + "']");
                                if (node.length > 0) {
                                    node.prop("checked", true);
                                    node.trigger("change");
                                }
                            }
                        }
                    } catch (e) {
                        console.log("Invalid JSON: " + e);
                    }
                });
            }, 100);
        }
    }).observe(document.body, {
        childList: true,
        subtree: true
    });

    $(".multipleSelectorSelect").click(function () {
        const il_signal = $(this).attr("modal-signal");

        $(this).trigger(il_signal,
            {
                'id' : il_signal, 'event' : 'click',
                'triggerer' : $(this),
                'options' : JSON.parse('[]')
            }
        );
    });

    $(".multipleSelectorReset").click(function () {
        const multipleSelector = $(this).parent().attr("multipleSelector");
        const $cont = $("#" + multipleSelector + "_cont");

        $cont.find(".multipleSelectorResult").val(JSON.stringify([])).trigger("input");
        $cont.find(".multiple-node").prop("checked", false);
    });

    $(".multipleSelectorResult").on("input", function () {
        const multipleSelector = $(this).parent().attr("multipleSelector");
        try {
            const value = JSON.parse($(this).val());

            let listHtml = "<ul>";

            if (value) {
                for (let i = 0; i < value.length; i++) {
                    listHtml += "<li>" + value[i].title + "</li>";
                }
            }

            listHtml += "</ul>";

            $("#" + multipleSelector + "_cont_txt").html(listHtml);
        } catch (e) {
            console.log("Invalid JSON: " + e);
        }
    });


    $(document).on("change", ".multiple-node", function() {
        const multipleSelector = $(this).attr("multipleSelector-id");

        const value = $(this).is(":checked");
        const $result = $("#" + multipleSelector + "_cont").find(".multipleSelectorResult");
        let result = $result.val();

        try {
            result = JSON.parse(result);
        } catch (e) {
            result = [];
        }

        result = result.filter(function (item) {
            return parseInt(item.id) !== parseInt($(this).attr("node-id"));
        }.bind(this));

        if (value) {
            result.push({
                id: parseInt($(this).attr("node-id")),
                title: $(this).attr("node-title")
            });
        }

        result.sort(function (a, b) {
            return a.id - b.id;
        });

        $result.val(JSON.stringify(result)).trigger("input");
    });

    new MutationObserver((mutations, obs) => {
        if ($(".multiple-node").length > 0) {
            obs.disconnect();

            setTimeout(function() {
                $(".multipleSelectorResult").each(function() {
                    const multipleSelector = $(this).parent().attr("multipleSelector");

                    try {
                        const value = JSON.parse($(this).val());

                        if (value) {
                            $(this).val("");
                            for (let i = 0; i < value.length; i++) {
                                const node = $("#" + multipleSelector + "_cont").find(".multiple-node[multipleSelector-id='" + multipleSelector + "'][node-id='" + value[i].id + "']");
                                if (node.length > 0) {
                                    node.prop("checked", true);
                                    node.trigger("change");
                                }
                            }
                        }
                    } catch (e) {
                        console.log("Invalid JSON: " + e);
                    }
                });
            }, 100);
        }
    }).observe(document.body, {
        childList: true,
        subtree: true
    });
});