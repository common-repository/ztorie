(function () {

    // if ((typeof pluginUrl != "undefined")){
        tinymce.PluginManager.add('ztorie_button_plugin', function (editor, url) {
            editor.addButton('ztorie', {
                tooltip: 'Ztorie Widget',
                image: ((typeof pluginUrl != "undefined") ? pluginUrl : '') + '/assets/images/wp_icon.png',
                cmd: "zt_accordion_shortcode_mce_button_popup"
            });
            let ztStoriesHtml = '';
            let ztDialogBody = '';
            let ztEmpty = false;
            if (typeof ztZtorieStories != "undefined") {

                if (ztZtorieStories.length > 0) {
                    for (let i = 0; i < ztZtorieStories.length; i++) {
                        ztStoriesHtml += "" +
                            "<div onclick='ztSelectZtoriePost(this)' class='zt-item zt-item-post' >" +
                            "    <img style='' src='" + ztZtorieStories[i].latest_thumb.first_thumb + "' alt=''>\n " +
                            "    <input type='hidden' name='code' value='" + ztZtorieStories[i].code + "'>\n" +
                            "    <input type='hidden' name='checked' value='0'>\n" +
                            "    <p>" + ztZtorieStories[i].title + "</p>\n" +
                            "    <div class='zt-buttons'>\n" +
                            "       <a target='_blank' href='https://app.ztorie.com/story/create/" + ztZtorieStories[i].code + "'>Edit</a>\n" +
                            // "       <a onclick='ztSelectZtoriePost(this)' code='" + ztorieStories[i].code + "' class='ztorie-use-story-post'>Use story</a>\n" +
                            "       <a onclick='ztOpenPreview(this)' class='zt-preview' >Preview</a>\n" +
                            "    </div>\n" +
                            "</div>";
                    }
                    ztDialogBody =
                        '<div style="width: 700px; padding: 10px;height: 400px">' +
                        '   <input type="hidden" name="ztorie_post_code" value="[]">' +
                        '   <p><b>Select one ore more stories to add</b></p>' +
                        '   <div class="zt-list">\n' +
                        ztStoriesHtml +
                        '   </div>' +
                        '</div>';

                } else {
                    ztDialogBody =
                        '<div>' +
                        '<h2 style="text-align: center;">Currenlty you have no stories, you can create one <a href="https://app.ztorie.com/dashboard">here.</a></h2>' +
                        '</div>';
                    ztEmpty = true;
                }
            } else {

                ztDialogBody =
                    '<div>' +
                    '<h2 style="text-align: center;">Please add an Ztorie Api key at <a href="/wp-admin/admin.php?page=ztorie_admin">config</a>.</h2>' +
                    '</div>';
                ztEmpty = true;
                console.log(ztDialogBody);
            }


            editor.addCommand('zt_accordion_shortcode_mce_button_popup', function (ui, v) {
                editor.windowManager.open({
                    title: 'Ztorie',
                    classes: 'items-panel',
                    body: [{
                        name: 'title',
                        multiline: true,
                        width: '600px',
                        height: '600px',
                        type: 'container',
                        html: ztDialogBody,
                    }],
                    onsubmit: function (e) {
                        if (!ztEmpty) {
                            let selected = JSON.parse(document.querySelector("input[name=ztorie_post_code]").value);
                            if (selected.length) {
                                if (selected.length > 1) {
                                    editor.execCommand("mceInsertContent", 0,
                                        '<div class=\'zt-container-parent\'>' +
                                        '<div class="zt-editor-title">*ZTORIE EMBED*</div>' +
                                        '<div data-carousel-tags=\' ["' + selected.join('","') + '"] \' data-type="carousel" data-carousel-type="default" data-carousel-count="' + selected.length + '"></div>' +
                                        '</div>\n');
                                } else {
                                    editor.execCommand("mceInsertContent", 0,
                                        '<div class=\'zt-container-parent\'>' +
                                        '<div class="zt-editor-title">*ZTORIE EMBED*</div>' +
                                        '<div data-carousel-tags=\' ["' + selected[0] + '"] \' data-type="carousel" data-carousel-type="latest" data-carousel-count="4"></div>' +
                                        '</div>\n');
                                }
                            } else {
                                return false;
                            }
                        } else {
                            return true;
                        }
                    }
                });
            });
        });

    // }
})();