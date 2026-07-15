/*
 * SPDX-FileCopyrightText: 2026 [ernolf] Raphael Gradenwitz <raphael.gradenwitz@googlemail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Helpers around the account label of an otpauth:// provisioning URI.
 *
 * otphp builds the URI path as rawurlencode('<issuer>:<account>') and rejects
 * a colon inside either part, so the first colon of the decoded segment
 * reliably separates the issuer from the account.
 */

/**
 * Decompose an otpauth URI into path prefix, issuer, account and query.
 *
 * @param {string} uri the otpauth:// provisioning URI
 * @return {{ prefix: string, issuer: ?string, account: string, query: string }} the URI parts
 */
function parts(uri) {
	const queryStart = uri.indexOf('?')
	const head = queryStart === -1 ? uri : uri.slice(0, queryStart)
	const query = queryStart === -1 ? '' : uri.slice(queryStart)
	const slash = head.indexOf('/', 'otpauth://'.length)
	const segment = decodeURIComponent(head.slice(slash + 1))
	const colon = segment.indexOf(':')
	return {
		prefix: head.slice(0, slash + 1),
		issuer: colon === -1 ? null : segment.slice(0, colon),
		account: colon === -1 ? segment : segment.slice(colon + 1),
		query,
	}
}

/**
 * The account label of the URI, split into the editable local part and the
 * fixed `@host` suffix (empty when the label carries no host).
 *
 * @param {string} uri the otpauth:// provisioning URI
 * @return {{ local: string, suffix: string }} the split account label
 */
export function accountLabel(uri) {
	const { account } = parts(uri)
	const at = account.lastIndexOf('@')
	return at === -1
		? { local: account, suffix: '' }
		: { local: account.slice(0, at), suffix: account.slice(at) }
}

/**
 * The same URI with the local part of its account label replaced. The issuer
 * prefix, the host suffix and every query parameter (secret, issuer, image, …)
 * survive. Colons are stripped (otpauth reserves them as separator) and a
 * blank replacement keeps the URI unchanged.
 *
 * @param {string} uri the otpauth:// provisioning URI
 * @param {string} local the new local part of the account label
 * @return {string} the patched URI
 */
export function withAccountLabel(uri, local) {
	const cleaned = local.replaceAll(':', '').trim()
	if (cleaned === '') {
		return uri
	}
	const { prefix, issuer, account, query } = parts(uri)
	const at = account.lastIndexOf('@')
	const suffix = at === -1 ? '' : account.slice(at)
	const label = (issuer === null ? '' : issuer + ':') + cleaned + suffix
	return prefix + encodeURIComponent(label) + query
}
