import { koComputable, addObservablesTo } from 'External/ko';

import { Notification } from 'Common/Enums';
import { defaultOptionsAfterRender } from 'Common/Utils';
import { folderListOptionsBuilder, sortFolders } from 'Common/Folders';
import { getNotification } from 'Common/Translator';

import { FolderUserStore } from 'Stores/User/Folder';

import Remote from 'Remote/User/Fetch';

import { AbstractViewPopup } from 'Knoin/AbstractViews';

import { setFolder, getFolderFromCacheList } from 'Common/Cache';
import { FolderModel } from 'Model/FolderCollection';

export class FolderCreatePopupView extends AbstractViewPopup {
	constructor() {
		super('FolderCreate');

		addObservablesTo(this, {
			name: '',
			subscribe: true,
			parentFolder: ''
		});

		this.parentFolderSelectList = koComputable(() =>
			folderListOptionsBuilder(
				[],
				[['', '']],
				oItem =>
					oItem ? (oItem.isSystemFolder() ? oItem.name() + ' ' + oItem.manageFolderSystemName() : oItem.name()) : '',
				FolderUserStore.namespace
					? item => !item.fullName.startsWith(FolderUserStore.namespace)
					: null,
				true
			)
		);

		this.defaultOptionsAfterRender = defaultOptionsAfterRender;
	}

	submitForm(form) {
		if (form.reportValidity()) {
			const data = new FormData(form);

			let parentFolderName = this.parentFolder();
			if (!parentFolderName && 1 < FolderUserStore.namespace.length) {
				data.set('parent', FolderUserStore.namespace.slice(0, FolderUserStore.namespace.length - 1));
			}

			Remote.abort('Folders').post('FolderCreate', FolderUserStore.foldersCreating, data)
				.then(
					data => {
						const folder = getFolderFromCacheList(parentFolderName),
							subFolder = FolderModel.reviveFromJson(data.Result),
							folders = (folder ? folder.subFolders : FolderUserStore.folderList);
						setFolder(subFolder);
						folders.push(subFolder);
						sortFolders(folders);
/*
						var collator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});
						console.log((folder ? folder.subFolders : FolderUserStore.folderList).sort(collator.compare));
*/
					},
					error => {
						FolderUserStore.folderListError(
							getNotification(error.code, '', Notification.CantCreateFolder)
							+ '.\n' + error.message);
					}
				);

			this.close();
		}
	}

	onShow() {
		this.name('');
		this.subscribe(true);
		this.parentFolder('');
	}
}
