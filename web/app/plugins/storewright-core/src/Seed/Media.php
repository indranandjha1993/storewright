<?php

declare(strict_types=1);

namespace Storewright\Core\Seed;

use ZipArchive;

final class Media {

	public function image( string $title, string $colour, string $url = '', string $credit = '' ): int {
		$existing = get_posts(
			[
				'post_type'      => 'attachment',
				'title'          => $title,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			]
		);
		if ( $existing ) {
			return (int) $existing[0];
		}
		$photo = $url !== '' ? download_url( $url, 30 ) : null;
		if ( is_string( $photo ) ) {
			$file = [
				'name'     => sanitize_title( $title ) . '.jpg',
				'tmp_name' => $photo,
			];
		} else {
			$path = wp_tempnam( $title . '.png' );
			$this->paint( $path, $title, $colour );
			$file   = [
				'name'     => sanitize_title( $title ) . '.png',
				'tmp_name' => $path,
			];
			$credit = '';
		}
		$id = media_handle_sideload( $file, 0, $title, [ 'post_excerpt' => $credit ] );
		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	public function pdf( string $title ): string {
		$target = $this->downloadsDir() . '/' . sanitize_title( $title ) . '.pdf';
		if ( ! file_exists( $target ) ) {
			file_put_contents( $target, $this->minimalPdf( $title ) );
		}
		return $this->url( $target );
	}

	public function zip( string $name ): string {
		$target = $this->downloadsDir() . '/' . $name . '.zip';
		if ( ! file_exists( $target ) ) {
			$zip = new ZipArchive();
			$zip->open( $target, ZipArchive::CREATE );
			foreach ( [ 'cart', 'account', 'search', 'menu' ] as $icon ) {
				$zip->addFromString( $icon . '.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><title>' . $icon . '</title><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/></svg>' );
			}
			$zip->addFromString( 'LICENSE', 'MIT' );
			$zip->close();
		}
		return $this->url( $target );
	}

	private function paint( string $path, string $title, string $colour ): void {
		$image       = imagecreatetruecolor( 1200, 1200 );
		[$r, $g, $b] = sscanf( $colour, '#%02x%02x%02x' );
		imagefill( $image, 0, 0, imagecolorallocate( $image, $r, $g, $b ) );
		$ink = imagecolorallocate( $image, 255, 255, 255 );
		imagefilledellipse( $image, 600, 540, 520, 520, imagecolorallocatealpha( $image, 255, 255, 255, 100 ) );
		$words = explode( ' ', $title );
		$y     = 980;
		foreach ( array_reverse( $words ) as $word ) {
			imagestring( $image, 5, 600 - (int) ( strlen( $word ) * 4.5 ), $y, $word, $ink );
			$y -= 24;
		}
		imagepng( $image, $path );
		imagedestroy( $image );
	}

	private function downloadsDir(): string {
		$upload = wp_upload_dir();
		$dir    = $upload['basedir'] . '/woocommerce_uploads/seed';
		wp_mkdir_p( $dir );
		return $dir;
	}

	private function url( string $path ): string {
		$upload = wp_upload_dir();
		return str_replace( $upload['basedir'], $upload['baseurl'], $path );
	}

	private function minimalPdf( string $title ): string {
		$text    = '(' . str_replace( [ '\\', '(', ')' ], [ '\\\\', '\(', '\)' ], $title ) . ') Tj';
		$stream  = "BT /F1 24 Tf 72 720 Td {$text} ET";
		$objects = [
			'<< /Type /Catalog /Pages 2 0 R >>',
			'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
			'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
			'<< /Length ' . strlen( $stream ) . " >>\nstream\n{$stream}\nendstream",
			'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
		];
		$pdf     = "%PDF-1.4\n";
		$offsets = [];
		foreach ( $objects as $i => $object ) {
			$offsets[] = strlen( $pdf );
			$pdf      .= ( $i + 1 ) . " 0 obj\n{$object}\nendobj\n";
		}
		$xref = strlen( $pdf );
		$pdf .= 'xref' . "\n0 " . ( count( $objects ) + 1 ) . "\n0000000000 65535 f \n";
		foreach ( $offsets as $offset ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offset );
		}
		$pdf .= "trailer\n<< /Size " . ( count( $objects ) + 1 ) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
		return $pdf;
	}
}
