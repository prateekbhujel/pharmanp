import React from 'react';
import { Button, Divider, Form, Input, InputNumber, Modal, Select, Switch, Upload } from 'antd';
import { PlusOutlined, PrinterOutlined, UploadOutlined } from '@ant-design/icons';
import { BarcodeInput } from '../../core/components/BarcodeInput';
import { BarcodeLabel, printBarcodeLabels } from '../../core/components/BarcodeLabel';
import { FormDrawer } from '../../core/components/FormDrawer';
import { normalizeFile } from './productPayload';

/**
 * Inline calculated display-price and margin, updated reactively via Form.useWatch.
 */
function CalculatedPricing({ form }) {
    const watchedMrp = Number(Form.useWatch('mrp', form) || 0);
    const watchedDiscount = Number(Form.useWatch('discount_percent', form) || 0);
    const watchedPurchasePrice = Number(Form.useWatch('purchase_price', form) || 0);
    const watchedConversion = Number(Form.useWatch('conversion', form) || 1);
    const displayPrice = watchedMrp - (watchedMrp * watchedDiscount / 100);
    const profit = displayPrice - (watchedPurchasePrice / (watchedConversion || 1));

    return (
        <>
            <Form.Item label="Calculated Display"><InputNumber readOnly value={Number(displayPrice.toFixed(2))} className="full-width" /></Form.Item>
            <Form.Item label="Profit"><InputNumber readOnly value={Number(profit.toFixed(2))} className="full-width" /></Form.Item>
        </>
    );
}

/**
 * Live barcode preview shown when a barcode value is entered in the form.
 */
function BarcodePreview({ form, editing }) {
    const watchedBarcode = Form.useWatch('barcode', form);
    const watchedName = Form.useWatch('name', form);

    if (!watchedBarcode) {
        return null;
    }

    return (
        <div className="barcode-form-preview">
            <BarcodeLabel value={watchedBarcode} caption={watchedName || 'Product barcode'} compact />
            <Button icon={<PrinterOutlined />} onClick={() => printBarcodeLabels([{ ...(editing || {}), name: watchedName, barcode: watchedBarcode }])}>Print</Button>
        </div>
    );
}

/**
 * Product create/edit drawer form plus the quick-add company/unit modal.
 *
 * @param {object} props
 * @param {boolean} props.open - Whether the drawer is visible.
 * @param {object|null} props.editing - Product record being edited, or null for create.
 * @param {object} props.form - Ant Design Form instance.
 * @param {object} props.quickForm - Ant Design Form instance for the quick-add modal.
 * @param {string|null} props.quickMaster - Which quick-add modal is open ('company'|'unit'|null).
 * @param {Function} props.onClose - Close the drawer.
 * @param {Function} props.onSubmit - Form submit handler.
 * @param {Function} props.onSubmitQuickMaster - Quick-add form submit handler.
 * @param {Function} props.onSetQuickMaster - Open quick-add modal.
 * @param {Function} props.onGenerateBarcode - Generate barcode from SKU/code/name.
 * @param {Function} props.onSyncPricing - Recalculate selling price on MRP/discount change.
 * @param {boolean} props.saving - Whether the form is submitting.
 * @param {object} props.meta - Lookup data { companies, units, divisions }.
 */
export function ProductFormDrawer({
    open,
    editing,
    form,
    quickForm,
    quickMaster,
    onClose,
    onSubmit,
    onSubmitQuickMaster,
    onSetQuickMaster,
    onGenerateBarcode,
    onSyncPricing,
    saving,
    meta,
}) {
    return (
        <>
            <FormDrawer
                title={editing ? 'Edit Product' : 'Add New Product'}
                open={open}
                onClose={onClose}
                footer={<Button type="primary" loading={saving} onClick={() => form.submit()} block>{editing ? 'Update' : 'Save'}</Button>}
                width={900}
            >
                <Form form={form} layout="vertical" onFinish={onSubmit} onValuesChange={onSyncPricing}>
                    <Divider orientation="left">Basic Information</Divider>
                    <div className="form-grid form-grid-3">
                        <Form.Item name="company_id" label="Company" rules={[{ required: true }]}>
                            <Select
                                showSearch
                                optionFilterProp="label"
                                placeholder="Select company"
                                options={meta.companies?.map((item) => ({ value: item.id, label: item.name }))}
                                dropdownRender={(menu) => (
                                    <>
                                        {menu}
                                        <Button type="link" icon={<PlusOutlined />} onClick={() => onSetQuickMaster('company')}>Quick add company</Button>
                                    </>
                                )}
                            />
                        </Form.Item>
                        <Form.Item name="unit_id" label="Unit" rules={[{ required: true }]}>
                            <Select
                                placeholder="Select unit"
                                options={meta.units?.map((item) => ({ value: item.id, label: item.name }))}
                                dropdownRender={(menu) => (
                                    <>
                                        {menu}
                                        <Button type="link" icon={<PlusOutlined />} onClick={() => onSetQuickMaster('unit')}>Quick add unit</Button>
                                    </>
                                )}
                            />
                        </Form.Item>
                        <Form.Item name="division_id" label="Division">
                            <Select
                                allowClear
                                showSearch
                                optionFilterProp="label"
                                placeholder="Select division"
                                options={meta.divisions?.map((item) => ({ value: item.id, label: item.code ? `${item.name} (${item.code})` : item.name }))}
                            />
                        </Form.Item>
                    </div>
                    <div className="form-grid form-grid-3">
                        <Form.Item name="product_code" label="Product Code"><Input placeholder="Optional unique code" /></Form.Item>
                        <Form.Item name="hs_code" label="HS Code"><Input placeholder="HS / customs code" /></Form.Item>
                        <Form.Item name="sku" label="SKU"><Input /></Form.Item>
                    </div>
                    <div className="form-grid form-grid-3">
                        <Form.Item label="Barcode">
                            <Input.Group compact style={{ display: 'flex' }}>
                                <Form.Item name="barcode" noStyle>
                                    <BarcodeInput placeholder="Optional barcode" />
                                </Form.Item>
                                <Button onClick={onGenerateBarcode}>Generate</Button>
                            </Input.Group>
                        </Form.Item>
                    </div>
                    <BarcodePreview form={form} editing={editing} />
                    <div className="form-grid form-grid-3">
                        <Form.Item name="name" label="Product Name" rules={[{ required: true }]}><Input placeholder="e.g. Paracetamol 500mg" /></Form.Item>
                        <Form.Item name="generic_name" label="Generic Name"><Input /></Form.Item>
                        <Form.Item name="packaging_type" label="Packaging Type"><Input placeholder="Box, strip, bottle..." /></Form.Item>
                    </div>
                    <div className="form-grid form-grid-3">
                        <Form.Item name="group_name" label="Group Name"><Input /></Form.Item>
                        <Form.Item name="manufacturer_name" label="Manufacturer"><Input /></Form.Item>
                        <Form.Item name="strength" label="Strength"><Input /></Form.Item>
                    </div>
                    <div className="form-grid form-grid-3">
                        <Form.Item name="conversion" label="Conversion"><InputNumber min={0.001} className="full-width" /></Form.Item>
                        <Form.Item name="rack_location" label="Rack Location"><Input /></Form.Item>
                    </div>

                    <Divider orientation="left">Pricing</Divider>
                    <div className="form-grid form-grid-4">
                        <Form.Item name="previous_price" label="Previous Price"><InputNumber min={0} className="full-width" /></Form.Item>
                        <Form.Item name="purchase_price" label="Purchase Price" rules={[{ required: true }]}><InputNumber min={0} className="full-width" /></Form.Item>
                        <Form.Item name="mrp" label="MRP" rules={[{ required: true }]}><InputNumber min={0} className="full-width" /></Form.Item>
                        <Form.Item name="cc_rate" label="CC Rate (%)"><InputNumber min={0} max={100} className="full-width" /></Form.Item>
                    </div>
                    <div className="form-grid form-grid-4">
                        <Form.Item name="discount_percent" label="Discount (%)"><InputNumber min={0} max={100} className="full-width" /></Form.Item>
                        <Form.Item name="selling_price" label="Display / Selling Price" rules={[{ required: true }]}><InputNumber min={0} className="full-width" /></Form.Item>
                        <CalculatedPricing form={form} />
                    </div>
                    <div className="form-grid form-grid-3">
                        <Form.Item name="reorder_level" label="Reorder Level" rules={[{ required: true }]}><InputNumber min={0} className="full-width" /></Form.Item>
                        <Form.Item name="reorder_quantity" label="Reorder Qty"><InputNumber min={0} className="full-width" /></Form.Item>
                        <div className="switch-row switch-row-inline">
                            <Form.Item name="is_batch_tracked" label="Batch Tracked" valuePropName="checked"><Switch /></Form.Item>
                            <Form.Item name="is_active" label="Active" valuePropName="checked"><Switch /></Form.Item>
                        </div>
                    </div>

                    <Divider orientation="left">Notes and Image</Divider>
                    <Form.Item name="notes" label="Internal Notes"><Input.TextArea rows={3} /></Form.Item>
                    {editing?.image_url && (
                        <div className="product-image-preview">
                            <img src={editing.image_url} alt="" />
                            <span>Current image</span>
                        </div>
                    )}
                    <Form.Item name="image_upload" label="Thumbnail Image" valuePropName="fileList" getValueFromEvent={normalizeFile}>
                        <Upload beforeUpload={() => false} maxCount={1} accept="image/*" listType="picture">
                            <Button icon={<UploadOutlined />}>Select Image</Button>
                        </Upload>
                    </Form.Item>
                    {editing?.image_url && <Form.Item name="remove_image" label="Remove current image" valuePropName="checked"><Switch /></Form.Item>}
                </Form>
            </FormDrawer>

            <Modal
                title={`Quick add ${quickMaster || ''}`}
                open={Boolean(quickMaster)}
                onCancel={() => onSetQuickMaster(null)}
                onOk={() => quickForm.submit()}
                destroyOnHidden
            >
                <Form form={quickForm} layout="vertical" onFinish={onSubmitQuickMaster}>
                    <Form.Item name="name" label="Name" rules={[{ required: true }]}>
                        <Input autoFocus />
                    </Form.Item>
                    {quickMaster === 'unit' && (
                        <div className="form-grid">
                            <Form.Item name="code" label="Code"><Input /></Form.Item>
                            <Form.Item name="type" label="Type" initialValue="both">
                                <Select options={[
                                    { value: 'both', label: 'Purchase and sale' },
                                    { value: 'purchase', label: 'Purchase only' },
                                    { value: 'sale', label: 'Sale only' },
                                ]} />
                            </Form.Item>
                            <Form.Item name="factor" label="Factor" initialValue={1}><InputNumber min={0.0001} className="full-width" /></Form.Item>
                        </div>
                    )}
                    {quickMaster === 'company' && (
                        <>
                            <div className="form-grid">
                                <Form.Item name="legal_name" label="Legal Name"><Input /></Form.Item>
                                <Form.Item name="pan_number" label="PAN"><Input /></Form.Item>
                            </div>
                            <div className="form-grid">
                                <Form.Item name="phone" label="Phone"><Input /></Form.Item>
                                <Form.Item name="default_cc_rate" label="Default CC %"><InputNumber min={0} max={100} className="full-width" /></Form.Item>
                            </div>
                        </>
                    )}
                </Form>
            </Modal>
        </>
    );
}
