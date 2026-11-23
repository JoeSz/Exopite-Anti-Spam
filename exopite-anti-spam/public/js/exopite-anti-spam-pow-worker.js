/* global module */
/**
 * Exopite Anti Spam: proof-of-work Web Worker.
 *
 * Message in:  { salt: string, bits: number }
 * Message out: { nonce: string }, the smallest decimal number for which SHA-256( salt + number )
 * starts with "bits" zero bits (checked on the server by Exopite_Anti_Spam_Public_Pow::check_solution()).
 *
 * The built-in SHA-256 below is used instead of crypto.subtle: for many short inputs it is much faster
 * (crypto.subtle needs one Promise per hash) and it works on HTTP too (crypto.subtle only on HTTPS).
 * No cookies, no storage, no requests.
 */
( function() {
	'use strict';

	const K = new Uint32Array( [
		0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5,
		0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
		0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
		0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
		0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85,
		0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
		0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3,
		0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2
	] );

	const W = new Uint32Array( 64 );

	/**
	 * SHA-256 of an ASCII string (salt: hex, nonce: digits).
	 *
	 * @param {string} text ASCII text.
	 * @return {Uint32Array} Hash as 8 words (big endian).
	 */
	function sha256( text ) {
		const length = text.length;
		const blocks = ( ( length + 8 ) >> 6 ) + 1;
		const words = new Uint32Array( blocks * 16 );
		let i;

		for ( i = 0; i < length; i++ ) {
			words[ i >> 2 ] |= text.charCodeAt( i ) << ( 24 - ( ( i & 3 ) * 8 ) );
		}

		words[ length >> 2 ] |= 0x80 << ( 24 - ( ( length & 3 ) * 8 ) );
		words[ ( blocks * 16 ) - 1 ] = length * 8;

		let h0 = 0x6a09e667, h1 = 0xbb67ae85, h2 = 0x3c6ef372, h3 = 0xa54ff53a,
			h4 = 0x510e527f, h5 = 0x9b05688c, h6 = 0x1f83d9ab, h7 = 0x5be0cd19;

		for ( let block = 0; block < blocks; block++ ) {
			for ( i = 0; i < 16; i++ ) {
				W[ i ] = words[ ( block * 16 ) + i ];
			}

			for ( i = 16; i < 64; i++ ) {
				const w15 = W[ i - 15 ];
				const w2 = W[ i - 2 ];
				const s0 = ( ( w15 >>> 7 ) | ( w15 << 25 ) ) ^ ( ( w15 >>> 18 ) | ( w15 << 14 ) ) ^ ( w15 >>> 3 );
				const s1 = ( ( w2 >>> 17 ) | ( w2 << 15 ) ) ^ ( ( w2 >>> 19 ) | ( w2 << 13 ) ) ^ ( w2 >>> 10 );
				W[ i ] = ( W[ i - 16 ] + s0 + W[ i - 7 ] + s1 ) | 0;
			}

			let a = h0, b = h1, c = h2, d = h3, e = h4, f = h5, g = h6, h = h7;

			for ( i = 0; i < 64; i++ ) {
				const S1 = ( ( e >>> 6 ) | ( e << 26 ) ) ^ ( ( e >>> 11 ) | ( e << 21 ) ) ^ ( ( e >>> 25 ) | ( e << 7 ) );
				const ch = ( e & f ) ^ ( ~e & g );
				const t1 = ( h + S1 + ch + K[ i ] + W[ i ] ) | 0;
				const S0 = ( ( a >>> 2 ) | ( a << 30 ) ) ^ ( ( a >>> 13 ) | ( a << 19 ) ) ^ ( ( a >>> 22 ) | ( a << 10 ) );
				const maj = ( a & b ) ^ ( a & c ) ^ ( b & c );
				const t2 = ( S0 + maj ) | 0;

				h = g;
				g = f;
				f = e;
				e = ( d + t1 ) | 0;
				d = c;
				c = b;
				b = a;
				a = ( t1 + t2 ) | 0;
			}

			h0 = ( h0 + a ) | 0;
			h1 = ( h1 + b ) | 0;
			h2 = ( h2 + c ) | 0;
			h3 = ( h3 + d ) | 0;
			h4 = ( h4 + e ) | 0;
			h5 = ( h5 + f ) | 0;
			h6 = ( h6 + g ) | 0;
			h7 = ( h7 + h ) | 0;
		}

		return new Uint32Array( [ h0, h1, h2, h3, h4, h5, h6, h7 ] );
	}

	/**
	 * Does the hash start with "bits" zero bits?
	 *
	 * @param {Uint32Array} hash Hash words.
	 * @param {number}      bits Number of zero bits.
	 * @return {boolean} True if solved.
	 */
	function hasZeroBits( hash, bits ) {
		let i = 0;

		for ( ; bits >= 32; bits -= 32, i++ ) {
			if ( 0 !== hash[ i ] ) {
				return false;
			}
		}

		return 0 === bits || 0 === ( hash[ i ] >>> ( 32 - bits ) );
	}

	/**
	 * Search the solution.
	 *
	 * @param {string} salt Salt of the challenge.
	 * @param {number} bits Number of zero bits.
	 * @return {string} Nonce.
	 */
	function solve( salt, bits ) {
		for ( let nonce = 0; nonce < 1e12; nonce++ ) {
			if ( hasZeroBits( sha256( salt + nonce ), bits ) ) {
				return String( nonce );
			}
		}

		return '';
	}

	// Node.js (tests): export the functions, no worker.
	if ( 'undefined' !== typeof module && module.exports ) {
		module.exports = { sha256, hasZeroBits, solve };
		return;
	}

	self.onmessage = function( event ) {
		const data = event.data || {};
		const salt = String( data.salt || '' );
		const bits = parseInt( data.bits, 10 ) || 0;

		if ( ! /^[0-9a-f]{1,64}$/.test( salt ) || bits < 1 || bits > 30 ) {
			self.postMessage( { nonce: '' } );
			return;
		}

		self.postMessage( { nonce: solve( salt, bits ) } );
	};
}() );
