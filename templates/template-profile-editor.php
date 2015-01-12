<?php
/*
Template name: Gestion de mon profil
*/
$current_user = wp_get_current_user();
?>
    <?php if(!$current_user->exists()): ?>
        <h1 class="main-title"><?php _e("Connexion obligatoire", "extra-events"); ?></h1>
        <div class="wrapper content">
            <p><?php _e("Vous devez avoir déjà effectué une réservation et avoir un compte pour pouvoir éditer vos informations personnelles.", "extra-events"); ?></p>
            <p>
                <a class="button" href="<?php echo wp_login_url("/"); ?>"><?php _e("Se connecter", "extra-events"); ?></a>
                <a class="button" href="<?php echo site_url("/"); ?>"><?php _e("Revenir à la page d'accueil", "extra-events"); ?></a>
            </p>
        </div>
    <?php
    else:
        $custom_fields = get_option( 'em_user_fields' );
        echo Extra_Profile_Editor::$statusMessage;
        ?>
        <h1 class="main-title"><?php the_second_title(); ?></h1>
        <form name="profile-editor" id="profile-editor" action="./" method="post">
            <fieldset>
                <legend><?php _e("Informations personnelles", "extra-events"); ?></legend>
                <?php if ($current_user->has_prop('user_login')): ?>
                <p>
                    <label for="user_login"><?php _e('Nom d\'utilisateur', 'extra-events'); ?></label>
                    <input type="text" name="user_login" id="user_login" value="<?php echo esc_attr($current_user->get('user_login')); ?>" disabled="disabled" />
                </p>
                <?php endif; ?>
                <?php if ($current_user->has_prop('user_email')): ?>
                <p>
                    <label for="user_email"><?php _e('Email', 'extra-events'); ?></label>
                    <input type="email" name="user_email" id="user_email" value="<?php echo esc_attr($current_user->get('user_email')); ?>" />
                </p>
                <?php endif; ?>
                <?php if ($current_user->has_prop('first_name')): ?>
                <p>
                    <label for="first_name"><?php _e('Prénom', 'extra-events'); ?></label>
                    <input type="text" name="first_name" id="first_name" value="<?php echo esc_attr($current_user->get('first_name')); ?>" />
                </p>
                <?php endif; ?>
                <?php if ($current_user->has_prop('last_name', 'extra-events')): ?>
                <p>
                    <label for="last_name"><?php _e('Nom'); ?></label>
                    <input type="text" name="last_name" id="last_name" value="<?php echo esc_attr($current_user->get('last_name')); ?>" />
                </p>
                <?php endif; ?>
            </fieldset>
            <fieldset id="password">
                <legend><?php _e("Mot de passe", "extra-events"); ?></legend>
                <p>
                    <label for="pass1"><?php _e( 'Nouveau mot de passe', 'extra-events' ); ?></label>
                    <input type="password" name="pass1" id="pass1" class="regular-text" size="16" value="" autocomplete="off" />
                </p>
                <p>
                    <label for="pass2"><?php _e( 'Répéter le nouveau mot de passe', 'extra-events' ); ?></label>
                    <input name="pass2" type="password" id="pass2" class="regular-text" size="16" value="" autocomplete="off" />
                    <div id="pass-strength-result"><?php _e( 'Strength indicator' ); ?></div>
                    <p class="description indicator-hint"><?php _e( 'Hint: The password should be at least seven characters long. To make it stronger, use upper and lower case letters, numbers, and symbols like ! " ? $ % ^ &amp; ).' ); ?></p>
                </p>
            </fieldset>
            <?php if(isset($custom_fields) && !empty($custom_fields)): ?>
            <fieldset>
                <legend><?php _e("Autres informations", "extra-events"); ?></legend>
                <?php
                foreach($custom_fields as $custom_field): ?>
                <p>
                    <label for="<?php echo $custom_field['fieldid']; ?>"><?php echo $custom_field['label']; ?></label>
                    <?php echo Extra_Profile_Editor::$EM_FORM->output_field_input($custom_field, esc_attr($current_user->get($custom_field['fieldid']))); ?>
                </p>
                <?php endforeach; ?>
            </fieldset>
            <?php endif; ?>
            <?php wp_nonce_field('extra-profile-editor-nonce'); ?>
            <p>
                <button type="submit" class="button">Valider</button>
            </p>
        </form>
    <?php endif; ?>