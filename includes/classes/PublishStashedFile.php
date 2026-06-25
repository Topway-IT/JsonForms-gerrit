<?php
/**
 * Upload a file from the upload stash into the local file repo.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup Upload
 * @ingroup JobQueue
 */


namespace MediaWiki\Extension\JsonForms;

use ApiMain;
use Status;
use UploadBase;
use UploadFromStash;
use User;

class PublishStashedFile {

	/** @var User */
	private $user;

	/** @var UploadFromStash */
	private $upload;

	/** @var string */
	private $filekey;

	/** @var string */
	private $filename;

	/** @var string */
	private $comment;

	/** @var string */
	private $text;

	/** @var bool */
	private $watch;

	/** @var array */
	private $tags;

	/** @var string|null */
	private $watchlistExpiry;

	/** @var string|null */
	private $lastError;

	/**
	 * @param User $user
	 * @param string $filekey
	 * @param string $filename
	 * @param string $comment
	 * @param string $text
	 * @param bool $watch
	 * @param array $tags
	 * @param string|null $watchlistExpiry
	 */
	public function __construct(
		User $user,
		string $filekey,
		string $filename,
		string $comment,
		string $text,
		bool $watch = false,
		array $tags = [],
		?string $watchlistExpiry = null
	) {
		$this->user = $user;
		$this->filekey = $filekey;
		$this->filename = $filename;
		$this->comment = $comment;
		$this->text = $text;
		$this->watch = $watch;
		$this->tags = $tags;
		$this->watchlistExpiry = $watchlistExpiry;
		$this->lastError = null;
	}

	/**
	 * Publish the stashed file
	 *
	 * @return bool True on success, false on failure
	 */
	public function publish(): bool {
		try {
			if ( !$this->user->isRegistered() ) {
				$this->lastError = "Could not load the author user from session.";
				return false;
			}

			// Set initial status
			$this->setStatus( 'Poll', 'publish', Status::newGood() );

			// Initialize upload from stash
			$this->upload = new UploadFromStash( $this->user );
			$this->upload->initialize( $this->filekey, $this->filename );

			// Check if file already exists
			$file = $this->upload->getLocalFile();
			if ( $file && $file->exists() ) {
				return true;
			}

			// Verify the upload
			if ( !$this->verifyUpload() ) {
				return false;
			}

			// Perform the upload
			if ( !$this->performUpload() ) {
				return false;
			}

			// Cache the final info
			$this->setSuccessStatus();

			return true;

		} catch ( \Exception $e ) {
			$this->setErrorStatus( $e );
			$this->lastError = get_class( $e ) . ": " . $e->getMessage();
			return false;
		}
	}

	/**
	 * Get the last error message
	 *
	 * @return string|null
	 */
	public function getLastError(): ?string {
		return $this->lastError;
	}

	/**
	 * Get the uploaded file name
	 *
	 * @return string|null
	 */
	public function getUploadedFileName(): ?string {
		if ( $this->upload ) {
			$file = $this->upload->getLocalFile();
			if ( $file ) {
				return $file->getName();
			}
		}
		return null;
	}

	/**
	 * Get the image info array
	 *
	 * @return array|null
	 */
	public function getImageInfo(): ?array {
		if ( $this->upload ) {
			$apiMain = new ApiMain();
			return $this->upload->getImageInfo( $apiMain->getResult() );
		}
		return null;
	}

	/**
	 * Verify the upload
	 *
	 * @return bool True on success, false on failure
	 */
	private function verifyUpload(): bool {
		$verification = $this->upload->verifyUpload();

		if ( $verification['status'] !== UploadBase::OK ) {
			$status = Status::newFatal( 'verification-error' );
			$status->value = [ 'verification' => $verification ];

			$this->setStatus( 'Failure', 'publish', $status );
			$this->lastError = "Could not verify upload.";

			return false;
		}

		return true;
	}

	/**
	 * Perform the upload
	 *
	 * @return bool True on success, false on failure
	 */
	private function performUpload(): bool {
		$status = $this->upload->performUpload(
			$this->comment,
			$this->text,
			$this->watch,
			$this->user,
			$this->tags,
			$this->watchlistExpiry
		);

		if ( !$status->isGood() ) {
			$this->setStatus( 'Failure', 'publish', $status );
			$this->lastError = $status->getWikiText( false, false, 'en' );

			// Cleanup temporary file
			$this->upload->cleanupTempFile();

			return false;
		}

		// Cleanup temporary file
		$this->upload->cleanupTempFile();

		return true;
	}

	/**
	 * Set the session status
	 *
	 * @param string $result
	 * @param string $stage
	 * @param Status $status
	 */
	private function setStatus( string $result, string $stage, Status $status ): void {
		UploadBase::setSessionStatus(
			$this->user,
			$this->filekey,
			[
				'result' => $result,
				'stage' => $stage,
				'status' => $status
			]
		);
	}

	/**
	 * Set the success status with image info
	 */
	private function setSuccessStatus(): void {
		$apiMain = new ApiMain();
		$imageInfo = $this->upload->getImageInfo( $apiMain->getResult() );
		$fileName = $this->upload->getLocalFile()->getName();

		UploadBase::setSessionStatus(
			$this->user,
			$this->filekey,
			[
				'result' => 'Success',
				'stage' => 'publish',
				'filename' => $fileName,
				'imageinfo' => $imageInfo,
				'status' => Status::newGood()
			]
		);
	}

	/**
	 * Set the error status
	 *
	 * @param \Exception $e
	 */
	private function setErrorStatus( \Exception $e ): void {
		UploadBase::setSessionStatus(
			$this->user,
			$this->filekey,
			[
				'result' => 'Failure',
				'stage' => 'publish',
				'status' => Status::newFatal( 'api-error-publishfailed' )
			]
		);
	}
}
