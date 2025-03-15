/**
 * Hesap Böl Fonksiyonalitesi
 * Bu dosya, hesap bölme işlevlerini içerir
 */

// Hesap bölme modalını göster
function showSplitBillModal() {
    POS.sounds.play('buttonClick');
    
    // Formları sıfırla
    document.getElementById('even-split-form').style.display = 'none';
    document.getElementById('individual-split-form').style.display = 'none';
    
    // Seçimi sıfırla
    const splitOptions = document.querySelectorAll('.split-option');
    splitOptions.forEach(option => {
        option.classList.remove('selected');
    });
    
    // Verilen ürün listesini güncelle (eğer ilgili form varsa)
    if (document.getElementById('individual-split-form')) {
        updateIndividualSplitItems();
    }
    
    // Modal göster
    $('#splitBillModal').modal('show');
}

// Bölme türünü seç (eşit bölüşüm veya kişisel ödeme)
function selectSplitOption(option) {
    POS.sounds.play('buttonClick');
    
    // Seçimi güncelle
    const splitOptions = document.querySelectorAll('.split-option');
    splitOptions.forEach(opt => {
        if (opt.getAttribute('data-split-type') === option) {
            opt.classList.add('selected');
        } else {
            opt.classList.remove('selected');
        }
    });
    
    // İlgili formu göster
    if (option === 'even') {
        document.getElementById('even-split-form').style.display = 'block';
        document.getElementById('individual-split-form').style.display = 'none';
    } else if (option === 'individual') {
        document.getElementById('even-split-form').style.display = 'none';
        document.getElementById('individual-split-form').style.display = 'block';
        updateIndividualSplitItems();
    }
}

// Eşit bölüşüm hesaplama
function calculateEvenSplit() {
    POS.sounds.play('buttonClick');
    
    const peopleCount = parseInt(document.getElementById('split-people-count').value);
    
    if (peopleCount < 2) {
        Toastify({
            text: "Lütfen en az 2 kişi girin",
            duration: 3000,
            backgroundColor: "#e74c3c",
        }).showToast();
        return;
    }
    
    // Toplam tutarı al
    const totalAmount = parseFloat(document.getElementById('total-price').innerText.replace(/[^\d.]/g, ''));
    
    // Kişi başı tutarı hesapla
    const perPersonAmount = totalAmount / peopleCount;
    
    // Sonucu görüntüle
    const resultDiv = document.getElementById('even-split-result');
    resultDiv.innerHTML = `
        <div class="alert alert-success">
            <h5>Hesap Bölünme Sonucu</h5>
            <p>Toplam Hesap: ${totalAmount.toFixed(2)}₺</p>
            <p>Kişi Sayısı: ${peopleCount}</p>
            <p class="lead">Kişi Başı: <strong>${perPersonAmount.toFixed(2)}₺</strong></p>
        </div>
    `;
}

// Kişisel ödeme - ürünleri kişilere atama listesini güncelle
function updateIndividualSplitItems() {
    const container = document.querySelector('#individual-split-form .individual-split-container');
    if (!container) return;
    
    container.innerHTML = '';
    
    // Sepetteki ürünleri al
    const orderItems = document.querySelectorAll('.summary-item');
    
    if (orderItems.length === 0) {
        container.innerHTML = '<div class="alert alert-warning">Sepette ürün bulunmamaktadır.</div>';
        return;
    }
    
    orderItems.forEach(item => {
        const productId = item.getAttribute('data-product-id');
        const productName = item.querySelector('.item-name').innerText;
        const productPrice = parseFloat(item.querySelector('.item-price').innerText.replace(/[^\d.]/g, ''));
        const quantity = parseInt(item.querySelector('.quantity-value').innerText);
        const personAttr = item.getAttribute('data-person') || '';
        
        const splitItem = document.createElement('div');
        splitItem.className = 'split-item';
        splitItem.setAttribute('data-product-id', productId);
        
        splitItem.innerHTML = `
            <div class="split-item-details">
                <div class="split-item-name">${productName} x${quantity}</div>
                <div class="split-item-price">${(productPrice * quantity).toFixed(2)}₺</div>
            </div>
            <select class="person-select" onchange="assignPersonFromSelect(${productId}, this.value)">
                <option value="" ${personAttr === '' ? 'selected' : ''}>Ortak</option>
                <option value="person1" ${personAttr === 'person1' ? 'selected' : ''}>Kişi 1</option>
                <option value="person2" ${personAttr === 'person2' ? 'selected' : ''}>Kişi 2</option>
                <option value="person3" ${personAttr === 'person3' ? 'selected' : ''}>Kişi 3</option>
                <option value="person4" ${personAttr === 'person4' ? 'selected' : ''}>Kişi 4</option>
            </select>
        `;
        
        container.appendChild(splitItem);
    });
    
    // Kişi bazlı toplamları göster
    updatePersonTotalsDisplay();
}

// Kişi bazlı toplam tutarları göster
function updatePersonTotalsDisplay() {
    const personTotals = calculatePersonTotals();
    const resultDiv = document.getElementById('individual-split-result');
    if (!resultDiv) return;
    
    resultDiv.innerHTML = `
        <div class="card mt-3">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-users me-2"></i>Kişi Bazlı Toplam
            </div>
            <div class="card-body">
                <ul class="list-group">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Ortak
                        <span class="badge bg-primary rounded-pill">${personTotals[''].toFixed(2)}₺</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Kişi 1
                        <span class="badge bg-primary rounded-pill">${personTotals['person1'].toFixed(2)}₺</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Kişi 2
                        <span class="badge bg-primary rounded-pill">${personTotals['person2'].toFixed(2)}₺</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Kişi 3
                        <span class="badge bg-primary rounded-pill">${personTotals['person3'].toFixed(2)}₺</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Kişi 4
                        <span class="badge bg-primary rounded-pill">${personTotals['person4'].toFixed(2)}₺</span>
                    </li>
                </ul>
            </div>
        </div>
    `;
}

// Ürünü kişiye ata (select menüsünden)
function assignPersonFromSelect(productId, person) {
    const item = document.querySelector(`.summary-item[data-product-id="${productId}"]`);
    if (item) {
        item.setAttribute('data-person', person);
        updatePersonTotalsDisplay();
    }
}

// Kişi bazlı toplamları hesapla
function calculatePersonTotals() {
    const personTotals = {
        '': 0, // Ortak
        'person1': 0,
        'person2': 0,
        'person3': 0,
        'person4': 0
    };
    
    // Sepetteki ürünleri al
    const orderItems = document.querySelectorAll('.summary-item');
    
    orderItems.forEach(item => {
        const price = parseFloat(item.querySelector('.item-price').innerText.replace(/[^\d.]/g, ''));
        const quantity = parseInt(item.querySelector('.quantity-value').innerText);
        const total = price * quantity;
        const person = item.getAttribute('data-person') || '';
        
        personTotals[person] += total;
    });
    
    return personTotals;
}

// Fişleri yazdır
function printSplitReceipts() {
    const masaId = document.getElementById('masa_id').value;
    const splitOption = document.querySelector('.split-option.selected')?.getAttribute('data-split-type');
    
    if (splitOption === 'even') {
        // Eşit bölüşüm fişlerini yazdır
        const peopleCount = parseInt(document.getElementById('split-people-count').value);
        if (peopleCount < 2) {
            Toastify({
                text: "Lütfen en az 2 kişi girin",
                duration: 3000,
                backgroundColor: "#e74c3c",
            }).showToast();
            return;
        }
        
        const totalAmount = parseFloat(document.getElementById('total-price').innerText.replace(/[^\d.]/g, ''));
        const perPersonAmount = totalAmount / peopleCount;
        
        // Fişleri yazdırma kuyruğuna ekle
        addToSplitPrintQueue(masaId, 'even', peopleCount, perPersonAmount);
    } else if (splitOption === 'individual') {
        // Kişisel fişleri yazdır
        const personTotals = calculatePersonTotals();
        
        // Her kişinin toplamı 0'dan büyükse fiş yazdır
        let hasPersons = false;
        for (const person in personTotals) {
            if (person !== '' && personTotals[person] > 0) {
                hasPersons = true;
                break;
            }
        }
        
        if (!hasPersons) {
            Toastify({
                text: "Lütfen ürünleri kişilere atayın",
                duration: 3000,
                backgroundColor: "#e74c3c",
            }).showToast();
            return;
        }
        
        // Fişleri yazdırma kuyruğuna ekle
        addToIndividualPrintQueue(masaId, personTotals);
    } else {
        Toastify({
            text: "Lütfen bir bölme türü seçin",
            duration: 3000,
            backgroundColor: "#e74c3c",
        }).showToast();
    }
}

// Eşit bölüşüm fişini yazdırma kuyruğuna ekle
function addToSplitPrintQueue(masaId, splitType, peopleCount, perPersonAmount) {
    const orderItems = document.querySelectorAll('.summary-item');
    const items = [];
    
    orderItems.forEach(item => {
        const productName = item.querySelector('.item-name').innerText;
        const price = item.querySelector('.item-price').innerText;
        const quantity = item.querySelector('.quantity-value').innerText;
        const productId = item.getAttribute('data-product-id');
        
        items.push({
            product_id: productId,
            name: productName,
            price: price,
            quantity: quantity
        });
    });
    
    const receiptData = {
        splitType: 'even',
        peopleCount: peopleCount,
        perPersonAmount: perPersonAmount.toFixed(2) + "₺",
        tableId: masaId,
        tableName: "Masa " + masaId,
        dateTime: new Date().toISOString().slice(0, 19).replace('T', ' '),
        items: items,
        totalAmount: parseFloat(document.getElementById('total-price').innerText.replace(/[^\d.]/g, '')).toFixed(2) + "₺",
        taxRate: 8,
        taxAmount: (parseFloat(document.getElementById('tax').innerText.replace(/[^\d.]/g, ''))).toFixed(2) + "₺",
        subtotal: (parseFloat(document.getElementById('subtotal').innerText.replace(/[^\d.]/g, ''))).toFixed(2) + "₺"
    };
    
    // Yazdırma kuyruğuna ekle
    $.ajax({
        url: 'add_print_queue.php',
        method: 'POST',
        data: {
            masa_id: masaId,
            masa_adi: "Masa " + masaId,
            receipt_type: 'split',
            receipt_data: JSON.stringify(receiptData)
        },
        success: function(response) {
            if (response.success) {
                Toastify({
                    text: peopleCount + " kişi için fiş yazdırma isteği gönderildi",
                    duration: 3000,
                    backgroundColor: "#f1c40f",
                }).showToast();
                
                // Modalı kapat
                $('#splitBillModal').modal('hide');
            } else {
                Toastify({
                    text: "Fiş yazdırma isteği gönderilirken hata oluştu",
                    duration: 3000,
                    backgroundColor: "#e74c3c",
                }).showToast();
            }
        },
        error: function() {
            Toastify({
                text: "Sunucu ile iletişim hatası",
                duration: 3000,
                backgroundColor: "#e74c3c",
            }).showToast();
        }
    });
}

// Kişisel fişleri yazdırma kuyruğuna ekle
function addToIndividualPrintQueue(masaId, personTotals) {
    const orderItems = document.querySelectorAll('.summary-item');
    let requestCount = 0;
    let successCount = 0;
    
    // Her kişi için ayrı fiş oluştur
    for (const person in personTotals) {
        if (person !== '' && personTotals[person] > 0) {
            const personItems = [];
            
            orderItems.forEach(item => {
                if (item.getAttribute('data-person') === person) {
                    const productName = item.querySelector('.item-name').innerText;
                    const price = item.querySelector('.item-price').innerText;
                    const quantity = item.querySelector('.quantity-value').innerText;
                    const productId = item.getAttribute('data-product-id');
                    
                    personItems.push({
                        product_id: productId,
                        name: productName,
                        price: price,
                        quantity: quantity
                    });
                }
            });
            
            const personName = person === '' ? 'Ortak' : 'Kişi ' + person.charAt(person.length - 1);
            const personTotal = personTotals[person];
            const taxAmount = personTotal * 0.08;
            const subtotal = personTotal - taxAmount;
            
            const receiptData = {
                splitType: 'individual',
                personName: personName,
                tableId: masaId,
                tableName: "Masa " + masaId,
                dateTime: new Date().toISOString().slice(0, 19).replace('T', ' '),
                items: personItems,
                totalAmount: personTotal.toFixed(2) + "₺",
                taxRate: 8,
                taxAmount: taxAmount.toFixed(2) + "₺",
                subtotal: subtotal.toFixed(2) + "₺"
            };
            
            requestCount++;
            
            // Yazdırma kuyruğuna ekle
            $.ajax({
                url: 'add_print_queue.php',
                method: 'POST',
                data: {
                    masa_id: masaId,
                    masa_adi: "Masa " + masaId + " (" + personName + ")",
                    receipt_type: 'personal',
                    receipt_data: JSON.stringify(receiptData)
                },
                success: function(response) {
                    if (response.success) {
                        successCount++;
                        
                        if (successCount === requestCount) {
                            Toastify({
                                text: requestCount + " kişi için fiş yazdırma isteği gönderildi",
                                duration: 3000,
                                backgroundColor: "#f1c40f",
                            }).showToast();
                            
                            // Modalı kapat
                            $('#splitBillModal').modal('hide');
                        }
                    }
                }
            });
        }
    }
    
    if (requestCount === 0) {
        Toastify({
            text: "Yazdırılacak kişisel fiş bulunamadı",
            duration: 3000,
            backgroundColor: "#e74c3c",
        }).showToast();
    }
}