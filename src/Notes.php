<?php

namespace mklasen\Aether;

class Notes
{
    public function __construct()
    {
        $this->hooks();
    }

    public function hooks()
    {
        add_action('admin_menu', array($this, 'add_notes_submenu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_save_aether_notes', array($this, 'ajax_save_notes'));
    }

    public function add_notes_submenu()
    {
        add_submenu_page(
            'aether',
            'Notes',
            'Notes',
            'manage_options',
            'aether-notes',
            array($this, 'render_notes_page')
        );
    }

    public function register_settings()
    {
        register_setting('aether_notes_options', 'aether_notes', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_notes')
        ));
    }

    public function sanitize_notes($notes)
    {
        if (!is_array($notes)) {
            return array();
        }

        return array_map(function($note) {
            return array(
                'title' => sanitize_text_field($note['title']),
                'content' => sanitize_textarea_field($note['content'])
            );
        }, $notes);
    }

    public function ajax_save_notes() 
    {
        check_ajax_referer('aether_notes_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $notes = isset($_POST['notes']) ? $_POST['notes'] : array();
        $sanitized_notes = $this->sanitize_notes($notes);
        
        update_option('aether_notes', $sanitized_notes);
        wp_send_json_success();
    }

    public function render_notes_page()
    {
        $notes = get_option('aether_notes', array());
        ?>
        <div class="wrap">
            <h1>Aether Notes</h1>
            <div id="save-status" style="display: none; padding: 10px; color: #2e7d32; background: #e8f5e9; margin: 10px 0; border-radius: 4px;">
                Changes saved
            </div>
            <div id="aether-notes-container">
                <?php 
                if (!empty($notes)) {
                    foreach ($notes as $index => $note) {
                        $this->render_note_field($index, $note);
                    }
                }
                ?>
            </div>
            <button type="button" class="button" id="add-note">Add Note</button>
        </div>
        <script>
            jQuery(document).ready(function($) {
                let saveTimeout;
                const saveStatus = $('#save-status');
                
                function showSaveStatus() {
                    saveStatus.fadeIn();
                    setTimeout(() => {
                        saveStatus.fadeOut();
                    }, 2000);
                }

                function saveNotes() {
                    const notes = [];
                    $('.note-wrapper').each(function(index) {
                        notes[index] = {
                            title: $(this).find('.note-title').val(),
                            content: $(this).find('.note-content').val()
                        };
                    });

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'save_aether_notes',
                            nonce: '<?php echo wp_create_nonce('aether_notes_nonce'); ?>',
                            notes: notes
                        },
                        success: function() {
                            showSaveStatus();
                        }
                    });
                }

                function debouncedSave() {
                    clearTimeout(saveTimeout);
                    saveTimeout = setTimeout(saveNotes, 1000);
                }

                $('#add-note').on('click', function() {
                    const index = $('#aether-notes-container .note-wrapper').length;
                    const noteHtml = `
                        <div class="note-wrapper">
                            <input type="text" class="note-title" name="aether_notes[${index}][title]" placeholder="Note Title" style="width: 100%; margin-bottom: 10px;">
                            <textarea class="note-content" name="aether_notes[${index}][content]" rows="4" style="width: 100%; margin-bottom: 10px;"></textarea>
                            <button type="button" class="button remove-note">Remove Note</button>
                        </div>
                    `;
                    $('#aether-notes-container').append(noteHtml);
                });

                $('#aether-notes-container').on('input', '.note-title, .note-content', debouncedSave);

                $('#aether-notes-container').on('click', '.remove-note', function() {
                    $(this).closest('.note-wrapper').remove();
                    debouncedSave();
                });
            });
        </script>
        <style>
            .note-wrapper {
                margin-bottom: 20px;
                padding: 15px;
                background: #fff;
                border: 1px solid #ccc;
            }
            .note-title {
                font-weight: bold;
                margin-bottom: 10px;
            }
        </style>
        <?php
    }

    private function render_note_field($index, $note)
    {
        ?>
        <div class="note-wrapper">
            <input type="text" class="note-title" name="aether_notes[<?php echo esc_attr($index); ?>][title]" placeholder="Note Title" value="<?php echo esc_attr($note['title']); ?>" style="width: 100%; margin-bottom: 10px;">
            <textarea class="note-content" name="aether_notes[<?php echo esc_attr($index); ?>][content]" rows="4" style="width: 100%; margin-bottom: 10px;"><?php echo esc_textarea($note['content']); ?></textarea>
            <button type="button" class="button remove-note">Remove Note</button>
        </div>
        <?php
    }
}
