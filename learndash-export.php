<?php
/**
 * Plugin Name: LearnDash Exporter
 * Description: Export courses, students, and enrollments from LearnDash.
 * Version: 0.1.0
 * Author: Codex
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class LearnDash_Exporter {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_post_learndash_export', array( $this, 'handle_export' ) );
    }

    public function register_menu() {
        add_management_page(
            __( 'LearnDash Export', 'learndash-export' ),
            __( 'LearnDash Export', 'learndash-export' ),
            'manage_options',
            'learndash-export',
            array( $this, 'render_page' )
        );
    }

    public function render_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'LearnDash Export', 'learndash-export' ); ?></h1>
            <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
                <?php wp_nonce_field( 'learndash_export', 'learndash_export_nonce' ); ?>
                <input type="hidden" name="action" value="learndash_export" />
                <p>
                    <label>
                        <input type="checkbox" name="export[]" value="courses" />
                        <?php esc_html_e( 'Courses', 'learndash-export' ); ?>
                    </label>
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="export[]" value="students" />
                        <?php esc_html_e( 'Students', 'learndash-export' ); ?>
                    </label>
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="export[]" value="enrollments" />
                        <?php esc_html_e( 'Enrollments', 'learndash-export' ); ?>
                    </label>
                </p>
                <?php submit_button( __( 'Export', 'learndash-export' ) ); ?>
            </form>
        </div>
        <?php
    }

    public function handle_export() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'learndash-export' ) );
        }
        check_admin_referer( 'learndash_export', 'learndash_export_nonce' );

        $types = isset( $_POST['export'] ) ? (array) $_POST['export'] : array();
        if ( empty( $types ) ) {
            wp_safe_redirect( admin_url( 'tools.php?page=learndash-export' ) );
            exit;
        }

        $filename = 'learndash-export-' . date( 'Y-m-d' ) . '.csv';
        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        $output = fopen( 'php://output', 'w' );

        foreach ( $types as $type ) {
            switch ( $type ) {
                case 'courses':
                    $this->export_courses( $output );
                    break;
                case 'students':
                    $this->export_students( $output );
                    break;
                case 'enrollments':
                    $this->export_enrollments( $output );
                    break;
            }
        }
        fclose( $output );
        exit;
    }

    protected function export_courses( $output ) {
        fputcsv( $output, array( 'Course ID', 'Title' ) );
        if ( ! function_exists( 'ld_get_courses' ) ) {
            return;
        }
        $courses = ld_get_courses();
        if ( ! empty( $courses['results'] ) ) {
            foreach ( $courses['results'] as $course_id ) {
                $title = get_the_title( $course_id );
                fputcsv( $output, array( $course_id, $title ) );
            }
        }
    }

    protected function export_students( $output ) {
        fputcsv( $output, array( 'User ID', 'Username', 'Email' ) );
        $users = get_users( array( 'fields' => array( 'ID', 'user_login', 'user_email' ) ) );
        foreach ( $users as $user ) {
            fputcsv( $output, array( $user->ID, $user->user_login, $user->user_email ) );
        }
    }

    protected function export_enrollments( $output ) {
        fputcsv( $output, array( 'User ID', 'Course ID' ) );
        if ( ! function_exists( 'ld_get_mycourses' ) ) {
            return;
        }
        $users = get_users( array( 'fields' => array( 'ID' ) ) );
        foreach ( $users as $user ) {
            $courses = ld_get_mycourses( $user->ID );
            if ( empty( $courses ) ) {
                continue;
            }
            foreach ( $courses as $course_id ) {
                fputcsv( $output, array( $user->ID, $course_id ) );
            }
        }
    }
}

new LearnDash_Exporter();

