const pptxgen = require('pptxgenjs');

// Create presentation instance
const pptx = new pptxgen();

// Set layout to 16:9 (default is LAYOUT_16x9 = 10" × 5.625")
pptx.setHeading(1, 28, 'Вред вейпов и табака: угроза здоровью', { color: '1E2761', font: 'Arial' });
pptx.setSubheading(2, 18, 'Для пропаганды здорового образа жизни', { color: '4A4E69', font: 'Arial' });

// ===================== Slide 1: Title =====================
const slide1 = pptx.addSlide({});
slide1.addText('Вред вейпов и табака:', 2, 2, { font: 'Arial', fontSize: 32, color: '1E2761', bold: true });
slide1.addText('Угроза здоровью', 2, 60, { font: 'Arial', fontSize: 32, color: '1E2761', bold: true });
slide1.addText('Для пропаганды здорового образа жизни', 2, 108, { font: 'Arial', fontSize: 16, color: '4A4E69' });
// Decorative bottom ribbon shape
slide1.addShape('RECTANGLE', 0, 350, 10, 30, { fill: '1E2761' });

// ===================== Slide 2: What are vapes? =====================
const slide2 = pptx.addSlide();
slide2.addText('Что такое вейпы?', 2, 2, { font: 'Arial', fontSize: 28, color: '1E2761', bold: true });
slide2.addText('• Электронные сигареты (e-cigarettes)', 2, 50, { font: 'Arial', fontSize: 16, color: '333333' });
slide2.addText('• Возобновляемое вдыхание аэрозоля', 2, 70, { font: 'Arial', fontSize: 16, color: '333333' });
slide2.addText('• Основные компоненты: никотин, ароматизаторы, пропиленгликоль, вегетариновое масло', 2, 90, { font: 'Arial', fontSize: 16, color: '333333' });
slide2.addText('• Обычно содержат формальдегид, акролеин и другие токсичные вещества', 2, 110, { font: 'Arial', fontSize: 16, color: '333333' });
// Red circle placeholder
slide2.addShape('CIRCLE', 2, 150, 12, 12, { fill: 'F96167' });

// ===================== Slide 3: Harmful chemicals =====================
const slide3 = pptx.addSlide();
slide3.addText('Химические вещества: вейпы vs табак', 2, 2, { font: 'Arial', fontSize: 28, color: '1E2761', bold: true });
slide3.addText('Вейпы:', 2, 50, { font: 'Arial', fontSize: 18, color: 'F96167', bold: true });
slide3.addText('• Никотин', 2, 75, { font: 'Arial', fontSize: 16, color: '333333' });
slide3.addText('• Пропиленгликоль', 2, 95, { font: 'Arial', fontSize: 16, color: '333333' });
slide3.addText('• Ароматизаторы (например, диацетил)', 2, 115, { font: 'Arial', fontSize: 16, color: '333333' });
slide3.addText('• Формальдегид, ацетальдегид, акролеин', 2, 135, { font: 'Arial', fontSize: 16, color: '333333' });
slide3.addText('Табачный дым:', 2, 160, { font: 'Arial', fontSize: 18, color: '2F3C7E', bold: true });
slide3.addText('• 7000+ химических веществ', 2, 185, { font: 'Arial', fontSize: 16, color: '333333' });
slide3.addText('• Минимум 70 известных канцерогенов', 2, 205, { font: 'Arial', fontSize: 16, color: '333333' });
slide3.addText('• Таран, печенье, оксид углерода', 2, 225, { font: 'Arial', fontSize: 16, color: '333333' });
// Comparison circles
slide3.addShape('CIRCLE', 2, 260, 12, 12, { fill: 'F96167' }); // red for vape
slide3.addShape('CIRCLE', 2, 280, 12, 12, { fill: '2F3C7E' }); // navy for tobacco

// ===================== Slide 4: X-ray comparison =====================
const slide4 = pptx.addSlide();
slide4.addText('Рентгенография легких', 2, 2, { font: 'Arial', fontSize: 28, color: '1E2761', bold: true });
slide4.addText('Здоровье — Пациент-вейпер — Пациент-курильщик', 2, 50, { font: 'Arial', fontSize: 16, color: '4A4E69' });
// Placeholder boxes for X-ray images
slide4.addShape('RECTANGLE', 2, 70, 180, 150, { fill: 'FFFFFF', border: 'CCCCCC', dash: 'dash' }); // Healthy
slide4.addShape('RECTANGLE', 220, 70, 180, 150, { fill: 'FFFFFF', border: 'CCCCCC', dash: 'dash' }); // Vaper
slide4.addShape('RECTANGLE', 420, 70, 180, 150, { fill: 'FFFFFF', border: 'CCCCCC', dash: 'dash' }); // Smoker
// Labels under boxes
slide4.addText('Здоровые легкие', 2, 230, { font: 'Arial', fontSize: 12, color: '2C5F2D' });
slide4.addText('Легкие от вейпинга', 220, 230, { font: 'Arial', fontSize: 12, color: 'F96167' });
slide4.addText('Легкие от курения', 420, 230, { font: 'Arial', fontSize: 12, color: '2F3C7E' });
// Descriptions
slide4.addText('• Здоровье: чистые лёгкие', 2, 260, { font: 'Arial', fontSize: 14, color: '333333' });
slide4.addText('• Вейперы: тёмные пятна, воспаление', 220, 260, { font: 'Arial', fontSize: 14, color: 'F96167' });
slide4.addText('• Курильщики: хронический острит, разрушение', 420, 260, { font: 'Arial', fontSize: 14, color: '2F3C7E' });

// ===================== Slide 5: Diseases from vaping =====================
const slide5 = pptx.addSlide();
slide5.addText('Болезни органов дыхания от вейпов', 2, 2, { font: 'Arial', fontSize: 28, color: '1E2761', bold: true });
slide5.addText('EVALI (E-cigarette or Vaping Product Use-associated Lung Injury)', 2, 55, { font: 'Arial', fontSize: 16, color: 'F96167', bold: true });
slide5.addText('Симптомы: одышка, боль в груди, лихорадка', 2, 80, { font: 'Arial', fontSize: 16, color: '333333' });
slide5.addText('Быстрее всего прогрессирующее легочное повреждение', 2, 105, { font: 'Arial', fontSize: 16, color: '333333' });
slide5.addText('Возможность респираторной недостаточности', 2, 125, { font: 'Arial', fontSize: 16, color: '333333' });
slide5.addText('Встречается в основном у молодежи и молодых взрослых', 2, 145, { font: 'Arial', fontSize: 16, color: '333333' });
slide5.addText('Статистика: ~2800 hospitalization cases in US (2020)', 2, 170, { font: 'Arial', fontSize: 16, color: '333333' });
slide5.addText 'Серьезные случаи могут привести к пересадке легких', 2, 190, { font: 'Arial', fontSize: 16, color: 'F96167' };
// Red warning circle
slide5.addShape('CIRCLE', 2, 220, 12, 12, { fill: 'F96167' });

// ===================== Slide 6: Diseases from tobacco =====================
const slide6 = pptx.addSlide();
slide6.addText('Болезни органов дыхания от курения', 2, 2, { font: 'Arial', fontSize: 28, color: '1E2761', bold: true });
slide6.addText('Рак легких — причина №1 смерти от рака по миру', 2, 55, { font: 'Arial', fontSize: 16, color: '2F3C7E', bold: true });
slide6.addText('Хроническая обструктивная порокодыхающая болезнь (ХОБП)', 2, 80, { font: 'Arial', fontSize: 16, color: '333333' });
slide6.addText('Хронический бронхит', 2, 100, { font: 'Arial', fontSize: 16, color: '333333' });
slide6.addText('Плюмба и эмфизема', 2, 120, { font: 'Arial', fontSize: 16, color: '333333' });
slide6.addText('Статистика: cigaretки — причина ~85% смертей от рака легких', 2, 145, { font: 'Arial', fontSize: 16, color: '333333' });
slide6.addText 'Около 8 миллионов смертей в год из-за табачного курения (WHO)', 2, 170, { font: 'Arial', fontSize: 16, color: '333333' });
slide6.addText 'Увеличен риск туберкулеза и пневмонии', 2, 190, { font: 'Arial', fontSize: 16, color: '333333' };
// Navy circle
slide6.addShape('CIRCLE', 2, 220, 12, 12, { fill: '2F3C7E' });

// ===================== Slide 7: Comparative statistics =====================
const slide7 = pptx.addSlide();
slide7.addText('Сравнительная статистика', 2, 2, { font: 'Arial', fontSize: 28, color: '1E2761', bold: true });
slide7.addText('Индекс вреда: Вейпы vs Курение', 2, 50, { font: 'Arial', fontSize: 16, color: '4A4E69' });
// Vape bar
slide7.addShape('RECTANGLE', 2, 90, 150, 30, { fill: 'F96167' });
slide7.addText('Вейпы', 2, 95, { font: 'Arial', fontSize: 14, color: 'FFFFFF', bold: true });
slide7.addText('~2800 hospitalizations (2020, USA)', 2, 120, { font: 'Arial', fontSize: 12, color: 'FFFFFF' });
// Tobacco bar
slide7.addShape('RECTANGLE', 2, 150, 150, 30, { fill: '2F3C7E' });
slide7.addText('Курение', 2, 155, { font: 'Arial', fontSize: 14, color: 'FFFFFF', bold: true });
slide7.addText('~8 млн смертей в год (WHO)', 2, 180, { font: 'Arial', fontSize: 12, color: 'FFFFFF' });
// Comparison notes
slide7.addText('Вейпы: меньше канцерогенов, но риск EVALI', 2, 210, { font: 'Arial', fontSize: 14, color: 'F96167' });
slide7.addText 'Курение: 7000+ химикалов, 70+ канцерогенов', 2, 230, { font: 'Arial', fontSize: 14, color: '2F3C7E' };

// ===================== Slide 8: Benefits of quitting =====================
const slide8 = pptx.addSlide();
slide8.addText('Польза отказа от курения и вейпинга', 2, 2, { font: 'Arial', fontSize: 28, color: '1E2761', bold: true });
slide8.addText '• Через 20 минут: давление нормализуется', 2, 55, { font: 'Arial', fontSize: 16, color: '333333' };
slide8.addText '• Через 12 часов: уровень углерода в крови падает', 2, 75, { font: 'Arial', fontSize: 16, color: '333333' };
slide8.addText '• Через 3 месяца: функция легких улучшается на 5-10%', 2, 95, { font: 'Arial', fontSize: 16, color: '333333' };
slide8.addText '• Через 1 год: риск сердечного заболевания уменьшается в 2 раза', 2, 115, { font: 'Arial', fontSize: 16, color: '333333' };
slide8.addText '• Через 5–15 лет: риск рака легких снижается до уровня некурящих', 2, 135, { font: 'Arial', fontSize: 16, color: '333333' };
slide8.addShape('CIRCLE', 2, 170, 12, 12, { fill: '2F3C7E' });

// ===================== Slide 9: Conclusions =====================
const slide9 = pptx.addSlide();
slide9.addText 'Выводы и призыв', 2, 2, { font: 'Arial', fontSize: 28, color: '1E2761', bold: true };
slide9.addText '• Вейпы не безвредны: содержат токсичные вещества', 2, 55, { font: 'Arial', fontSize: 16, color: 'F96167' };
slide9.addText '• Курение — главная причина предотвратимых смертей', 2, 75, { font: 'Arial', fontSize: 16, color: '2F3C7E' });
slide9.addText '• Рентген показывает явные изменения у обоих', 2, 95, { font: 'Arial', fontSize: 16, color: '333333' };
slide9.addText '• Отказ от никотина — лучшее решение для здоровья', 2, 115, { font: 'Arial', fontSize: 16, color: '1E2761' };
slide9.addText '• Забота о здоровье — основа здорового образа жизни', 2, 135, { font: 'Arial', fontSize: 16, color: '4A4E69' });
// Footer note
slide9.addText 'Источники: WHO, CDC, медицинские исследования 2020–2024', 2, 200, { font: 'Arial', fontSize: 12, color: '999999' });

// ===================== Write the file =====================
pptx.writeFile('vaping_harm_presentation.pptx');
console.log('Presentation created: vaping_harm_presentation.pptx');