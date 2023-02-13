import { AbstractCollectionModel } from 'Model/AbstractCollection';
import { EmailModel, addressparser } from 'Model/Email';
import { forEachObjectValue } from 'Common/Utils';

'use strict';

export class EmailCollectionModel extends AbstractCollectionModel
{
	/**
	 * @param {?Array} json
	 * @returns {EmailCollectionModel}
	 */
	static reviveFromJson(items) {
		return super.reviveFromJson(items, email => EmailModel.reviveFromJson(email));
	}

	/**
	 * @param {string} text
	 * @returns {EmailCollectionModel}
	 */
	static fromString(str) {
		let list = new this();
		list.fromString(str);
		return list;
	}

	/**
	 * @param {boolean=} friendlyView = false
	 * @param {boolean=} wrapWithLink = false
	 * @returns {string}
	 */
	toString(friendlyView, wrapWithLink) {
		return this.map(email => email.toLine(friendlyView, wrapWithLink)).join(', ');
	}

	/**
	 * @param {string} text
	 */
	fromString(str) {
		if (str) {
			let items = {}, key;
			addressparser(str).forEach(item => {
				item = new EmailModel(item.address, item.name);
				// Make them unique
				key = item.email || item.name;
				if (key && (item.name || !items[key])) {
					items[key] = item;
				}
			});
			forEachObjectValue(items, item => this.push(item));
		}
	}

}
