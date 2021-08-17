<?php
/**
 * Use this trait in conjunction wpPostAble interface only.
 *
 * By using this trait, you should call wpPostAble( $post_type, $post_id ) method
 * in the beginning __construct() of your class.
 * Pass to it two parameters
 *      $post_type      string      WP post type, associated with your class
 *      $post_id        int         Post ID for existing post, or nothing for creating new post
 */

namespace iTRON;

use iTRON\Exception\wppaCreatePostException;
use iTRON\Exception\wppaLoadPostException;
use iTRON\Exception\wppaSavePostException;
use iTRON\wpPostAble;
use WP_Error;
use WP_Post;

trait wpPostAbleTrait{
	/**
	 * @var string
	 */
	private $post_type = '';

	/**
	 * @var WP_Post
	 */
	protected $post;

	/**
	 * @var string
	 * @see wp_insert_post()
	 */
	public $status = 'draft';

	/**
	 * Call this method in the beginning __construct() of your class.
	 *
	 * @param string $post_type
	 * @param int $post_id
	 *
	 * @return $this
	 * @throws wppaCreatePostException
	 * @throws wppaLoadPostException
	 */
	private function wpPostAble( string $post_type, int $post_id = 0 ): self {
		static $called = 0;
		if ( $called++ > 0 ) return $this;

		$this->post_type = $post_type;

		if ( empty( $post_id ) ){
			$post_id = wp_insert_post([
				'post_type'     => $this->getPostType(),
				'post_status'   => $this->getStatus(),
				'post_title'    => '',
				'post_content'  => 'Empty.',
			], true );

			if ( empty( $post_id ) || is_wp_error( $post_id ) ){
				$error = empty( $post_id ) ? new WP_Error() : $post_id;
				/** @var wpPostAble $this */
				throw new wppaCreatePostException( $this, $error, $error->get_error_messages() );
			}
		}

		return $this->loadPost( $post_id );
	}

	public function getPost(): WP_Post{
		return $this->post;
	}

	public function getPostType(): string{
		return $this->post_type;
	}

	/**
	 * @throws wppaSavePostException
	 */
	public function savePost(): self {
		$result = wp_update_post( $this->post, true );
		if ( empty( $result ) || is_wp_error( $result ) ){
			$error = empty( $result ) ? new WP_Error() : $result;
			/** @var wpPostAble $this */
			throw new wppaSavePostException( $this, $error, $error->get_error_messages() );
		}
		return $this;
	}

	/**
	 * Loads and initiates all Group data from WP post.
	 * @return $this
	 * @throws wppaLoadPostException
	 */
	public function loadPost( int $post_id ): self {

		if (
			empty( $post_id ) ||
			empty( $post = get_post( $post_id ) ) ||
			! apply_filters( __CLASS__ . '\wpPostAbleTrait\loadPost\equal_post_type',
				apply_filters( '\wpPostAbleTrait\loadPost\equal_post_type', $post->post_type === $this->post_type, __CLASS__ ), __CLASS__
			)
		){
			/** @var wpPostAble $this */
			throw new wppaLoadPostException( $post_id, $this, "Incorrect post id [ $post_id ]");
		}

		$this->post = $post;

		do_action_ref_array( '\wpPostAbleTrait\loadPost\loading', [ & $this, __CLASS__ ] );
		do_action_ref_array( __CLASS__ . '\wpPostAbleTrait\loadPost\loading', [ & $this, __CLASS__ ] );
		return $this;
	}

	public function getTitle(): string{
		return $this->post->post_title;
	}

	public function setTitle( string $title ): self {
		$this->post->post_title = $title;
		return $this;
	}

	public function getStatus(): string{
		return $this->status;
	}

	public function setStatus( string $status ): self {
		$this->status = $status;
		$this->post->post_status = $status;
		return $this;
	}

	/**
	 * @throws wppaSavePostException
	 */
	public function publish(): self {
		$this->setStatus( 'publish' );
		return $this->savePost();
	}

	/**
	 * @throws wppaSavePostException
	 */
	public function draft(): self {
		$this->setStatus( 'draft' );
		return $this->savePost();
	}
}
