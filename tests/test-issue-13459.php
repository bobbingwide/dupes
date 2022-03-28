<?php
/**
 * @copyright (C) Copyright Bobbing Wide 2022
 */

class Tests_issue_13459 extends BW_UnitTestCase {

    static private $saved_permalink_structure = null;

    private $duplicate_title;
	/**
	 * Permalink structure to test. Blank is for Plain permalinks ie https://example.com/?p=123
	 * @var string
	 */
    private $structure = '';
	/** 
	 * set up logic
	 * 
	 * - ensure any database updates are rolled back
	 */

    /**
     * Array of duplicate posts.
     * @var
     */
	private $posts;

	function setUp(): void {
		parent::setUp();
		$this->set_duplicate_title();
		$this->clear_all_duplicates();
		$this->posts = [];
	}

	function tearDown(): void {
	    $this->clear_all_duplicates();
    }

    static function setUpBeforeClass() : void {
        self::save_permalink_structure();
    }

    static function tearDownAfterClass() : void {
	    self::restore_permalink_structure();
	    self::flush_rules();
    }

    static function save_permalink_structure() {
        global $wp_rewrite;
        self::$saved_permalink_structure = $wp_rewrite->permalink_structure;
    }

    static function restore_permalink_structure() {
        global $wp_rewrite;
        $wp_rewrite->set_permalink_structure( self::$saved_permalink_structure );
    }

    /**
     * Don't set a timestamp in a duplicate title.
     *
     * It makes it harder to remove duplicates when tests fail
     * and the tests are being run in situ.
     */
    function set_duplicate_title() {
	    $this->duplicate_title = 'duplicate title TRAC 13459';
	    //$this->duplicate_title .= date( '(Y-m-d H:i:s)');
    }

    /**
     * Since we're forcing commits in order to perform wp_remote_get() calls
     * we need to ensure we've cleaned up any previous duplicates.
     *
     */
	function clear_all_duplicates() {
	    $posts = $this->fetch_all_duplicates();
	    $this->delete_all_duplicates( $posts );
        parent::commit_transaction();
        $refetched = $this->fetch_all_duplicates();
        $this->assertCount( 0, $refetched );
    }

    /**
     * Fetches all duplicates ( up to 10 ) with the duplicate title.
     *
     * @return int[]|WP_Post[]
     */
    function fetch_all_duplicates() {
	    $args = [ 'post_type' => 'any',
            'post_status' => 'any',
            'title' => $this->duplicate_title,
            'number_posts' => 10
            ];
	    $posts = get_posts( $args );
	    //print_r( $posts );
	    //gob();
        return $posts;
    }

    function delete_all_duplicates( $posts ) {
        foreach ($posts as $post) {
            //echo "deleting: " . $post->post_title . ' ' . $post->ID;
            if ( $this->duplicate_title === $post->post_title ) {
                $error = wp_delete_post($post->ID, true );
                //print_r( $error );
            } else {
                echo "Error: Not deleting. We shouldn't have fetched this.";
                print_r( $post );
            }

        }
    }

	/**
     * Sets the permalink structure.
     *
     */
	function set_permalink_structure( $structure='/%postname%/') {
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( $structure );
	}

    /**
     * Flushes the rewrite rules.
     *
     * Before calling wp_remote_get() we need to
     * call flush_rules() to write the updated rewrite rules
     * and commit the database updates.
     * The updates are reversed during tear down processing.
     */
	static function flush_rules() {
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
		parent::commit_transaction();
	}

	/**
	 * Generates post content to uniquely identify the post.
     *
	 * Includes the permalink structure.
     * The post ID placeholder ( %ID% ) is updated by update_post_content().
	 *
	 * @param $title
	 * @param $type
	 * @param $status
	 * @return string
	 */
	function generate_post_content( $title, $type, $status ) {
	    $content = $this->findable_paragraph();
        $content .= "$title:$type:$status:%ID%:";
        $content .= $this->structure;
        $content .= "</p>";
        return $content;
    }

    function findable_paragraph() {
	    return '<p class="duplicate-title-trac-13459">';
    }

	/**
	 * Helper method to create a post.
	 */
	function create_post( $type, $status ) {
	    $title = $this->duplicate_title;
		$content = $this->generate_post_content( $title, $type, $status);
		$args = array( 'post_content' => $content
			, 'post_type' => $type
			, 'post_title' => $title
			, 'post_status' => $status
			);
		$post = $this->factory->post->create( $args );
        $fetched = get_post( $post );
		$this->assertEquals( $content, $fetched->post_content );
		$this->update_post_content( $fetched );
		$fetched = get_post( $post );
		return $fetched;
	}

	/**
	 * Sets the post ID in the content and excerpt. We can't do it on the insert.
	 *
	 * @param $post
	 */
	function update_post_content( $post ) {
	    $postarr = [];
	    $postarr['ID'] = $post->ID;
	    $postarr['post_content'] = str_replace( '%ID%', $post->ID, $post->post_content );
	    $postarr['post_excerpt'] = $postarr['post_content'];
	    //print_r( $postarr );
	    wp_update_post( $postarr );
    }

	/**
	 * Attempts to fetch the post by its permalink.
	 *
	 * @param $post
	 */
	function fetch_post_by_permalink( $post ) {
		$permalink = get_permalink( $post );
		//echo "Fetching: " . $permalink;
        $result = $this->fetch_post( $permalink );
        return $result;
	}

	function fetch_post_by_id( $post ) {
	    $permalink = $post->guid;
	    //print_r( $post );
	    //echo $permalink;
        $result = $this->fetch_post( $permalink );
	    return $result;
    }

	/**
     * Attempt to fetch a post by link.
     *
     * The link doesn't have to be the permalink of the post.
     * It can be in different format such as:
     *
     * - ?p=nnnn - ie plain
     * - year/month/postname
     * - postname&post_type=type

     */
	function fetch_post( $permalink ) {
        //echo "Fetching: " . $permalink;
        $args = [ 'sslverify' => false ];
        $result  = wp_remote_get( $permalink, $args);
        //echo "Result: ";
        $this->assertNotWPError( $result );
        if ( ! is_wp_error( $result ) ) {
            $response_code = wp_remote_retrieve_response_code( $result );
            //echo "Response code: " . $response_code;
            //print_r( $response_code );
            $message = "Permalink: " . $permalink;
            $message .= " Structure: " . $this->structure;
            $this->assertEquals( 200, $response_code, $message );
            if ( 200 === $response_code ) {
                $response = wp_remote_retrieve_body( $result );
                //print_r( $response );
                $result = $response;
            } else {
                print_r( $result );
            }
        }
        return $result;
    }

    /**
     * Checks that the post's content is in the fetched result.
     * @param $fetched
     * @param $post
     */
	function check_fetched( $fetched, $post )  {

	    $pos = strpos( $fetched, $this->findable_paragraph() );
	    $this->assertGreaterThan( 0, $pos );
        $fetched = substr( $fetched, $pos );
        //echo "Fetched:";
        //echo $fetched;
        $endpara = strpos( $fetched, "</p>");
        $fetched = substr( $fetched, 0, $endpara + 4 );
        // Checks for needle in haystack. Reverse of strpos().
	    $this->assertEquals( $post->post_content, $fetched, "Structure: " . $this->structure );
    }

	/**
	 * These are sensible permalink structures.
	 *
	 * Non-sensible permalink structures are any structure which doesn't contain the %postname% or %post_id%
	 * eg /%monthnum%/
	 * @return string[]
	 */
    function permalink_structures() {
		$structures = [
			'',
			'/%year%/%monthnum%/%day%/%postname%/',
			'/%year%/%monthnum%/%postname%/',
			'/%post_id%/',
			'/%post_id%/%postname%/',
			'/%postname%/'
		];
		return $structures;
    }

    /**
     * Generate duplicate posts of the selected post type.
     *
     * @param $types array of required post types
     */
    function generate_duplicates( $types ) {
        $this->set_permalink_structure( $this->structure );
        self::flush_rules();
        $this->clear_all_duplicates();

        $this->posts[0] = $this->create_post( $types[0], 'publish');
        $this->posts[1] = $this->create_post( $types[1], 'publish');

        parent::commit_transaction();
    }

	/**
     * Tests accessing posts with non-duplicated slugs.
     *
     * Two posts with the same title should have different slugs
     * and be individually accessible by their permalinks.
     */
	function test_duplicate_post_post() {
		//$structure = '/%postname%/';
        $this->generate_duplicates( ['post', 'post'] );

        $this->assertNotEquals( $this->posts[0]->ID, $this->posts[1]->ID );
        $this->assertNotEquals( $this->posts[0]->post_name, $this->posts[1]->post_name );

        $this->access_duplicates_by_permalink();
        $this->access_duplicates_by_id();
    }

    /**
     * Tests accessing a duplicate slug: page and post.
     *
     * When we request the post by its permalink then we should get the post.
     * When we request the page by its permalink then we should get the page.
     *
     * Obviously this can't happen when both posts have the same slug.
     * We need to indicate what we're looking for.
     * But WordPress doesn't yet realise that there's a problem.
     * And doesn't support using a `post_type=` attribute to enable the differentiation.
     */
    function test_duplicate_page_post() {
        $this->generate_duplicates( ['page', 'post'] );


        $this->assertNotEquals( $this->posts[0]->ID, $this->posts[1]->ID );
        // This will fail if and when the post's permalink is different from the page's
        $this->assertEquals( $this->posts[0]->post_name, $this->posts[1]->post_name );

        // This will fail while the page is being fetched instead of the post.
        // due to the post's permalink being the same as the page's.
        $this->access_duplicates_by_permalink();
        /**
         * Access the duplicated posts by their post IDs
         * Do we get here when an assertion has already failed?
         */
        //$this->access_duplicates_by_id();
    }

    function test_duplicate_page_post_access_by_ID() {
        $this->generate_duplicates( ['page', 'post'] );


        $this->assertNotEquals( $this->posts[0]->ID, $this->posts[1]->ID );
        // This will fail if and when the post's permalink is different from the page's
        $this->assertEquals( $this->posts[0]->post_name, $this->posts[1]->post_name );

        /**
         * Access the duplicated posts by their post IDs
         * Do we get here when an assertion has already failed?
         */
        $this->access_duplicates_by_id();
    }

	/**
	 * Tests duplicate posts combinations for a range of permalink structures.
	 */
	function test_permalink_structures() {
		$structures = $this->permalink_structures();
		foreach ( $structures as $structure ) {
			$this->structure = $structure;
			$this->test_duplicate_post_post();
		}
	}

	/**
	 * Tests duplicate page/post combinations for a range of permalink structures.
	 */
	function test_permalink_structures_page_post() {
		$structures = $this->permalink_structures();
		foreach ( $structures as $structure ) {
			$this->structure = $structure;
			//$this->test_duplicate_post_post();
			$this->test_duplicate_page_post();
		}
	}

    /**
     * Tests duplicate page/post combinations for a range of permalink structures.
     */
    function test_permalink_structures_page_post_access_by_ID() {
        $structures = $this->permalink_structures();
        foreach ( $structures as $structure ) {
            $this->structure = $structure;
            //$this->test_duplicate_post_post();
            $this->test_duplicate_page_post_access_by_ID();
        }
    }

	function access_duplicates_by_permalink() {
        $fetched1 = $this->fetch_post_by_permalink( $this->posts[0] );
        $this->check_fetched( $fetched1, $this->posts[0] );
        $fetched2 = $this->fetch_post_by_permalink( $this->posts[1] );
        $this->check_fetched( $fetched2, $this->posts[1] );
    }

	function access_duplicates_by_id() {
        $fetched3 = $this->fetch_post_by_id( $this->posts[0] );
        $this->check_fetched( $fetched3, $this->posts[0] );
        $fetched4 = $this->fetch_post_by_id( $this->posts[1] );
        $this->check_fetched( $fetched4, $this->posts[1] );
    }

}